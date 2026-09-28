<?php

use App\Billing\Gateways\ManualBankTransferGateway;
use App\Billing\Gateways\PaystackGateway;
use App\Billing\Gateways\StripeGateway;
use App\Billing\PaymentGatewayManager;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    seedPlans();
    $this->user = actingAsTenantUser();

    config([
        'billing.gateways.paystack.secret_key' => 'sk_test_secret',
        'billing.gateways.manual.enabled' => true,
        'billing.gateways.manual.accounts.NGN' => [
            'bank_name' => 'Test Bank',
            'account_name' => 'EliteSender Ltd',
            'account_number' => '0123456789',
        ],
        'billing.gateways.stripe.secret_key' => null,
    ]);

    $this->invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'number' => 'EZ-TEST-00001',
        'status' => Invoice::STATUS_OPEN,
        'currency' => 'NGN',
        'subtotal' => 1_200_000,
        'total' => 1_200_000,
    ]);
});

/*
|--------------------------------------------------------------------------
| Registry
|--------------------------------------------------------------------------
*/

it('only offers gateways that are configured and support the currency', function (): void {
    $manager = new PaymentGatewayManager;

    expect(array_keys($manager->availableFor('NGN')))->toEqualCanonicalizing(['paystack', 'manual'])
        // Stripe has no key set, so it must never be offered.
        ->and($manager->availableFor('USD'))->not->toHaveKey('stripe');
});

it('reports stripe as unconfigured without a secret key', function (): void {
    expect((new StripeGateway)->isConfigured())->toBeFalse();

    config(['billing.gateways.stripe.secret_key' => 'sk_live_x']);

    expect((new StripeGateway)->isConfigured())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Paystack
|--------------------------------------------------------------------------
*/

it('initialises a paystack transaction in minor units', function (): void {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'reference' => 'x'],
    ])]);

    $session = (new PaystackGateway)->checkout($this->invoice, 'https://app.test/callback');

    expect($session->requiresRedirect())->toBeTrue()
        ->and($session->redirectUrl)->toBe('https://checkout.paystack.com/abc');

    Http::assertSent(function ($request) {
        // Paystack quotes minor units, which is how we store money — no
        // conversion, and therefore no rounding error.
        return $request['amount'] === 1_200_000 && $request['currency'] === 'NGN';
    });
});

it('treats a non-success paystack verification as a failure', function (): void {
    Http::fake(['*/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['status' => 'abandoned', 'gateway_response' => 'Customer left'],
    ])]);

    $result = (new PaystackGateway)->verify('ref_x');

    expect($result->successful)->toBeFalse()
        ->and($result->failureReason)->toBe('Customer left');
});

it('accepts a correctly signed paystack webhook and rejects a forged one', function (): void {
    $payload = json_encode(['event' => 'charge.success', 'data' => [
        'id' => 99, 'reference' => 'ref_hook', 'amount' => 1_200_000, 'currency' => 'NGN',
    ]]);

    $signature = hash_hmac('sha512', $payload, 'sk_test_secret');

    $this->call('POST', route('billing.webhook', 'paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $payload)->assertOk();

    // A forged signature is an authentication failure, not a bad request.
    $this->call('POST', route('billing.webhook', 'paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => str_repeat('0', 128),
    ], $payload)->assertStatus(401);
});

it('credits an invoice from a verified webhook exactly once', function (): void {
    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'invoice_id' => $this->invoice->id,
        'gateway' => 'paystack',
        'reference' => 'ref_hook',
        'status' => Payment::STATUS_PENDING,
        'currency' => 'NGN',
        'amount' => 1_200_000,
    ]);

    $payload = json_encode(['event' => 'charge.success', 'data' => [
        'id' => 4242, 'reference' => 'ref_hook', 'amount' => 1_200_000, 'currency' => 'NGN',
    ]]);

    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_secret'),
    ];

    // Providers retry. Replaying the identical event must not pay twice.
    $this->call('POST', route('billing.webhook', 'paystack'), [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', route('billing.webhook', 'paystack'), [], [], [], $headers, $payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and(Payment::withoutGlobalScopes()->where('gateway_ref', '4242')->count())->toBe(1);
});

it('ignores webhook events it does not act on', function (): void {
    $payload = json_encode(['event' => 'transfer.failed', 'data' => []]);

    $this->call('POST', route('billing.webhook', 'paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_secret'),
    ], $payload)->assertOk()->assertJsonPath('message', 'Ignored.');
});

it('404s an unknown gateway webhook', function (): void {
    $this->postJson(route('billing.webhook', 'bitcoin'), [])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Stripe signature (verifiable without an account)
|--------------------------------------------------------------------------
*/

it('validates stripe signatures and rejects replayed ones', function (): void {
    config(['billing.gateways.stripe.webhook_secret' => 'whsec_test']);

    $gateway = new StripeGateway;
    $body = '{"type":"checkout.session.completed"}';

    $sign = fn (int $timestamp) => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    $fresh = Illuminate\Http\Request::create('/', 'POST', server: ['HTTP_STRIPE_SIGNATURE' => $sign(time())], content: $body);
    expect($gateway->verifyWebhook($fresh))->toBeTrue();

    // A genuine, correctly signed event from an hour ago must not be
    // replayable — that is the entire point of the timestamp.
    $stale = Illuminate\Http\Request::create('/', 'POST', server: ['HTTP_STRIPE_SIGNATURE' => $sign(time() - 3600)], content: $body);
    expect($gateway->verifyWebhook($stale))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manual bank transfer
|--------------------------------------------------------------------------
*/

it('shows bank details and a matchable reference instead of redirecting', function (): void {
    $session = (new ManualBankTransferGateway)->checkout($this->invoice, 'https://app.test/callback');

    expect($session->requiresRedirect())->toBeFalse()
        ->and($session->instructions['Account number'])->toBe('0123456789')
        ->and($session->instructions['Payment reference'])->toBe($session->reference)
        // Read down a phone line, so no ambiguous glyphs.
        ->and($session->reference)->toMatch('/^EZ[ACDEFGHJKLMNPQRTUVWXYZ2346789]{10}$/');
});

it('never self-confirms a bank transfer', function (): void {
    // Only a human decision, recorded against an admin id, can settle this.
    expect((new ManualBankTransferGateway)->verify('EZABCDEFGH')->successful)->toBeFalse();
});

it('records a pending payment and shows instructions on the invoice', function (): void {
    $this->post(route('billing.checkout.start', $this->invoice->id), ['gateway' => 'manual'])
        ->assertRedirect(route('billing.invoices.show', $this->invoice->id))
        ->assertSessionHas('bank_instructions');

    // The attempt row is what lets support reconcile a payment whose callback
    // never arrived.
    expect(Payment::withoutGlobalScopes()->where('gateway', 'manual')->where('status', 'pending')->count())->toBe(1);
});

it('rejects a gateway that cannot settle the invoice currency', function (): void {
    config(['billing.gateways.manual.accounts.NGN' => []]);

    $this->post(route('billing.checkout.start', $this->invoice->id), ['gateway' => 'manual'])
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('refuses to start checkout on an already paid invoice', function (): void {
    $this->invoice->forceFill(['status' => Invoice::STATUS_PAID, 'amount_paid' => $this->invoice->total])->save();

    $this->post(route('billing.checkout.start', $this->invoice->id), ['gateway' => 'manual'])
        ->assertStatus(422);
});
