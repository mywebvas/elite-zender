<?php

use App\Billing\BillingService;
use App\Billing\Gateways\StripeGateway;
use App\Billing\PaymentGatewayManager;
use App\Billing\PlanGate;
use App\Billing\StoredCredential;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;

/**
 * Stripe, end to end.
 *
 * Every call is faked at the HTTP boundary, which is the only honest way to
 * test a payment integration without a live account: the assertions are about
 * the exact request Stripe receives and the exact state we derive from its
 * reply.
 */
beforeEach(function (): void {
    seedPlans();

    config([
        'billing.gateways.stripe.secret_key' => 'sk_test_stripe',
        'billing.gateways.stripe.webhook_secret' => 'whsec_test',
        'billing.gateways.stripe.base_url' => 'https://api.stripe.com',
        'billing.gateways.paystack.secret_key' => null,
        'billing.default_currency' => 'USD',
    ]);

    $this->user = actingAsTenantUser(['role' => Role::OWNER]);
    $this->tenant = $this->user->tenant;
    $this->billing = app(BillingService::class);
    $this->gateway = new StripeGateway;
});

/*
|--------------------------------------------------------------------------
| Availability
|--------------------------------------------------------------------------
*/

it('appears at checkout as soon as a key is configured', function (): void {
    expect((new PaymentGatewayManager)->availableFor('USD'))->toHaveKey('stripe');

    config(['billing.gateways.stripe.secret_key' => null]);

    // …and vanishes again without one, so an unconfigured install can never
    // send a customer to a checkout that cannot complete.
    expect((new PaymentGatewayManager)->availableFor('USD'))->not->toHaveKey('stripe');
});

it('is not offered for a currency it cannot settle', function (): void {
    expect($this->gateway->supports('USD'))->toBeTrue()
        ->and($this->gateway->supports('NGN'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Checkout
|--------------------------------------------------------------------------
*/

it('creates a checkout session and saves the card for future renewals', function (): void {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
        'id' => 'cs_test_123',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
    ])]);

    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    $session = $this->gateway->checkout($invoice, 'https://app.test/callback');

    expect($session->requiresRedirect())->toBeTrue()
        ->and($session->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_123');

    Http::assertSent(function ($request) use ($invoice) {
        $body = $request->data();

        return $body['line_items[0][price_data][unit_amount]'] === $invoice->total
            && $body['line_items[0][price_data][currency]'] === 'usd'
            // Without this the subscription could never renew itself and every
            // month would drag the customer back through checkout.
            && $body['payment_intent_data[setup_future_usage]'] === 'off_session'
            && $body['metadata[invoice_id]'] === $invoice->getKey();
    });
});

it('drives the whole customer journey from choosing a plan to a paid invoice', function (): void {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions*' => Http::sequence()
            ->push(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])
            ->push(['data' => [[
                'id' => 'cs_1',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_1',
                'amount_total' => 5_900,
                'currency' => 'usd',
                'customer' => 'cus_1',
            ]]]),
        'api.stripe.com/v1/payment_intents/pi_1' => Http::response(['payment_method' => 'pm_1']),
        'api.stripe.com/v1/payment_methods/pm_1' => Http::response(['card' => ['brand' => 'visa', 'last4' => '4242']]),
    ]);

    $this->post(route('billing.subscribe'), ['plan' => 'growth'])->assertRedirect();
    $invoice = Invoice::withoutGlobalScopes()->sole();

    $this->post(route('billing.checkout.start', $invoice->id), ['gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

    $reference = Payment::withoutGlobalScopes()->where('status', Payment::STATUS_PENDING)->sole()->reference;

    $this->get(route('billing.checkout.callback', $invoice->id).'?reference='.$reference)
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('success');

    $subscription = app(PlanGate::class)->subscriptionFor($this->tenant);

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->plan->code)->toBe('growth')
        // The card is on file, so next month happens without the customer.
        ->and($subscription->card_last_four)->toBe('4242')
        ->and($subscription->canAutoRenew())->toBeTrue();
});

it('refuses to credit an unpaid session even if the customer opens the callback', function (): void {
    Http::fake(['api.stripe.com/v1/checkout/sessions*' => Http::sequence()
        ->push(['id' => 'cs_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_2'])
        ->push(['data' => [['id' => 'cs_2', 'payment_status' => 'unpaid']]]),
    ]);

    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());
    $this->post(route('billing.checkout.start', $invoice->id), ['gateway' => 'stripe']);

    $reference = Payment::withoutGlobalScopes()->sole()->reference;

    // Anyone can open the callback URL; only Stripe's own answer counts.
    $this->get(route('billing.checkout.callback', $invoice->id).'?reference='.$reference)
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_OPEN);
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

it('accepts a correctly signed webhook and settles the invoice once', function (): void {
    Http::fake([
        'api.stripe.com/v1/payment_intents/pi_hook' => Http::response(['payment_method' => 'pm_hook']),
        'api.stripe.com/v1/payment_methods/pm_hook' => Http::response(['card' => ['brand' => 'mastercard', 'last4' => '5555']]),
    ]);

    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'gateway' => 'stripe',
        'reference' => 'ezr_hook',
        'status' => Payment::STATUS_PENDING,
        'currency' => 'USD',
        'amount' => $invoice->total,
    ]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_hook',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_hook',
            'client_reference_id' => 'ezr_hook',
            'amount_total' => $invoice->total,
            'currency' => 'usd',
            'customer' => 'cus_hook',
        ]],
    ]);

    $timestamp = time();
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test'),
    ];

    // Stripe retries aggressively; the second delivery must change nothing.
    $this->call('POST', route('billing.webhook', 'stripe'), [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', route('billing.webhook', 'stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total)
        ->and(Payment::withoutGlobalScopes()->where('gateway_ref', 'pi_hook')->count())->toBe(1);
});

it('rejects a forged or replayed webhook', function (): void {
    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => []]]);

    $this->call('POST', route('billing.webhook', 'stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.str_repeat('a', 64),
    ], $payload)->assertStatus(401);

    // A genuine signature from an hour ago is still refused — that is what the
    // timestamp in the signature is for.
    $old = time() - 3600;

    $this->call('POST', route('billing.webhook', 'stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$old.',v1='.hash_hmac('sha256', $old.'.'.$payload, 'whsec_test'),
    ], $payload)->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Recurring
|--------------------------------------------------------------------------
*/

it('charges the saved card off-session at renewal', function (): void {
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response([
        'id' => 'pi_renewal',
        'status' => 'succeeded',
        'amount_received' => 5_900,
        'currency' => 'usd',
    ])]);

    $growth = Plan::where('code', 'growth')->sole();
    $subscription = $this->billing->activate($this->tenant, $growth, 'USD', 'stripe');
    $subscription->forceFill([
        'gateway_token' => 'pm_saved',
        'gateway_customer' => 'cus_saved',
        'current_period_end' => now()->subHour(),
    ])->save();

    $invoice = $this->billing->invoiceRenewal($subscription->fresh());

    expect($this->billing->attemptAutoCharge($subscription->fresh(), $invoice))->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['off_session'] ?? null) === 'true'
            && ($body['confirm'] ?? null) === 'true'
            && ($body['payment_method'] ?? null) === 'pm_saved'
            && ($body['customer'] ?? null) === 'cus_saved';
    });
});

it('surfaces a decline rather than swallowing it', function (): void {
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response([
        'error' => ['message' => 'Your card was declined.'],
    ], 402)]);

    $result = $this->gateway->chargeStored(
        $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole()),
        new StoredCredential(token: 'pm_bad', customer: 'cus_bad'),
    );

    expect($result->successful)->toBeFalse()
        // The customer needs to be told *why*, and 3-D Secure declines look
        // exactly like this.
        ->and($result->failureReason)->toBe('Your card was declined.');
});

it('does not roll back a payment when the card details cannot be read back', function (): void {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions*' => Http::sequence()
            ->push(['id' => 'cs_3', 'url' => 'https://checkout.stripe.com/c/pay/cs_3'])
            ->push(['data' => [[
                'id' => 'cs_3', 'payment_status' => 'paid', 'payment_intent' => 'pi_3',
                'amount_total' => 5_900, 'currency' => 'usd', 'customer' => 'cus_3',
            ]]]),
        // Stripe is having a bad day on the follow-up lookups.
        'api.stripe.com/v1/payment_intents/pi_3' => Http::response([], 500),
    ]);

    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());
    $this->post(route('billing.checkout.start', $invoice->id), ['gateway' => 'stripe']);
    $reference = Payment::withoutGlobalScopes()->sole()->reference;

    $this->get(route('billing.checkout.callback', $invoice->id).'?reference='.$reference);

    // Losing the card on file is a degraded renewal, never a lost payment.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and(app(PlanGate::class)->subscriptionFor($this->tenant)->gateway_token)->toBeNull();
});
