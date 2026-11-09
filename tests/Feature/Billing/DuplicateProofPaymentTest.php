<?php

use App\Billing\BillingService;
use App\Billing\PaymentResult;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedPlans();
    $this->user = actingAsTenantUser(['role' => Role::OWNER]);
    $this->tenant = $this->user->tenant;
    $this->billing = app(BillingService::class);
    Storage::fake('local');
});

it('strictly rejects duplicate payment credits on replayed gateway events', function (): void {
    $plan = Plan::where('code', 'growth')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    $paymentResult = PaymentResult::success(
        gateway: 'paystack',
        gatewayRef: 'psk_ref_unique_12345',
        reference: 'ez_ref_unique_12345',
        amount: $invoice->total,
        currency: $invoice->currency,
        raw: ['status' => 'success'],
    );

    // First arrival (e.g. customer redirect callback)
    $payment1 = $this->billing->recordPayment($invoice, $paymentResult);

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total)
        ->and($payment1->status)->toBe(Payment::STATUS_SUCCEEDED);

    // Replay arrival (e.g. retried webhook 5 seconds later)
    $payment2 = $this->billing->recordPayment($invoice, $paymentResult);

    // Must return the exact same payment record
    expect($payment2->id)->toBe($payment1->id)
        // Must NOT increase the amount paid
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total)
        // Must have exactly 1 payment record in the database
        ->and(Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('guarantees single subscription activation even under repeated payment signals', function (): void {
    $plan = Plan::where('code', 'growth')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    $result = PaymentResult::success(
        gateway: 'stripe',
        gatewayRef: 'pi_test_dup_guard',
        reference: 'ref_dup_guard',
        amount: $invoice->total,
        currency: $invoice->currency,
        raw: [],
    );

    // Simulate 3 rapid webhook deliveries of the same charge
    $this->billing->recordPayment($invoice, $result);
    $this->billing->recordPayment($invoice, $result);
    $this->billing->recordPayment($invoice, $result);

    $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();

    // Must have exactly ONE subscription for this tenant
    expect(Subscription::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and($subscription)->not->toBeNull()
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total);
});

it('renders double-click protection on the invoice payment forms', function (): void {
    config()->set('billing.gateways.manual.enabled', true);
    config()->set('billing.gateways.manual.accounts.USD.account_number', '1234567890');
    config()->set('billing.gateways.manual.accounts.USD.bank_name', 'Elite Bank');

    $plan = Plan::where('code', 'growth')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    $response = $this->withSession([
        'bank_instructions' => ['Bank' => 'Elite Bank', 'Account' => '1234567890'],
        'bank_reference' => 'EZTESTREF99',
    ])->get(route('billing.invoices.show', $invoice->id));

    $response->assertOk()
        ->assertSee('x-data="{ starting: false }"', escape: false)
        ->assertSee('x-data="{ submitting: false }"', escape: false);
});

it('settles manual bank transfer with exactly one payment record upon confirmation', function (): void {
    $plan = Plan::where('code', 'starter')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    $pendingPayment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'gateway' => 'manual',
        'reference' => 'EZMANUALTEST01',
        'status' => Payment::STATUS_PENDING,
        'currency' => $invoice->currency,
        'amount' => $invoice->total,
        'proof_path' => 'payment-proofs/test_proof.webp',
    ]);

    expect(Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(1);

    // Confirm transfer via BillingService
    $confirmed = $this->billing->confirmManualPayment(
        payment: $pendingPayment,
        amount: $invoice->total,
        note: 'Verified in bank portal',
        adminId: 'admin_test_1',
    );

    expect($confirmed->id)->toBe($pendingPayment->id)
        ->and($confirmed->status)->toBe(Payment::STATUS_SUCCEEDED)
        ->and($confirmed->proof_path)->toBe('payment-proofs/test_proof.webp')
        ->and($confirmed->gateway_ref)->toBe('manual_'.$pendingPayment->id)
        // Must maintain exactly ONE row in payments table (no duplicate row created)
        ->and(Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(1)
        // Invoice must be settled with exact single payment sum
        ->and($invoice->fresh()->isPaid())->toBeTrue()
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total);
});

it('handles callback refresh gracefully when invoice is already settled', function (): void {
    $plan = Plan::where('code', 'starter')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    // Simulate already settled payment
    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'gateway' => 'paystack',
        'reference' => 'ez_already_settled',
        'gateway_ref' => 'psk_already_settled',
        'status' => Payment::STATUS_SUCCEEDED,
        'currency' => $invoice->currency,
        'amount' => $invoice->total,
    ]);
    $invoice->update(['status' => Invoice::STATUS_PAID, 'amount_paid' => $invoice->total]);

    // Customer re-hits or refreshes callback URL
    $response = $this->get(route('billing.checkout.callback', [
        'invoice' => $invoice->id,
        'reference' => 'ez_already_settled',
    ]));

    // Must redirect safely to billing index with success, not throw "Unknown payment reference"
    $response->assertRedirect(route('billing.index'))
        ->assertSessionHas('success');
});

it('prevents uploading proof to an already paid invoice', function (): void {
    $plan = Plan::where('code', 'starter')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);
    $invoice->update(['status' => Invoice::STATUS_PAID, 'amount_paid' => $invoice->total]);

    $file = UploadedFile::fake()->image('receipt.jpg');

    $response = $this->post(route('billing.proof', $invoice->id), [
        'reference' => 'EZREF123',
        'proof' => $file,
    ]);

    $response->assertStatus(422);
});
