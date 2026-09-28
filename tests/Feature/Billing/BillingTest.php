<?php

use App\Billing\BillingService;
use App\Billing\PaymentResult;
use App\Billing\PlanGate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmtpAccount;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function (): void {
    seedPlans();
    $this->user = actingAsTenantUser(['role' => Role::OWNER]);
    $this->tenant = $this->user->tenant;
    $this->billing = app(BillingService::class);
    $this->planGate = app(PlanGate::class);
});

/*
|--------------------------------------------------------------------------
| Currency selection
|--------------------------------------------------------------------------
*/

it('bills Nigerian workspaces in naira and everyone else in dollars', function (): void {
    // Locally issued cards frequently fail on USD charges, so quoting dollars
    // to a naira-earning business is both a conversion and a delivery problem.
    $this->tenant->forceFill(['settings' => ['country' => 'NG']])->save();
    expect($this->billing->currencyFor($this->tenant->fresh()))->toBe('NGN');

    $this->tenant->forceFill(['settings' => ['country' => 'GB']])->save();
    expect($this->billing->currencyFor($this->tenant->fresh()))->toBe('USD');

    $this->tenant->forceFill(['settings' => []])->save();
    expect($this->billing->currencyFor($this->tenant->fresh()))->toBe('USD');
});

/*
|--------------------------------------------------------------------------
| Subscribing
|--------------------------------------------------------------------------
*/

it('switches to a free plan immediately without an invoice', function (): void {
    $this->post(route('billing.subscribe'), ['plan' => 'free'])
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('success');

    expect(Invoice::withoutGlobalScopes()->count())->toBe(0)
        ->and($this->planGate->subscriptionFor($this->tenant)->plan->code)->toBe('free');
});

it('raises an invoice for a paid plan and sends the user to pay it', function (): void {
    $this->post(route('billing.subscribe'), ['plan' => 'growth'])->assertRedirect();

    $invoice = Invoice::withoutGlobalScopes()->sole();

    expect($invoice->status)->toBe(Invoice::STATUS_OPEN)
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->total)->toBe(5_900)  // $59.00 in cents
        ->and($invoice->number)->toStartWith('EZ-');
});

it('refuses to self-serve a quoted plan', function (): void {
    $this->post(route('billing.subscribe'), ['plan' => 'enterprise'])
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('only lets owners and admins commit the workspace to spending', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->post(route('billing.subscribe'), ['plan' => 'growth'])->assertForbidden();
});

it('numbers invoices sequentially within a month', function (): void {
    $first = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'starter')->sole());
    $second = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    expect((int) substr($second->number, -5))->toBe((int) substr($first->number, -5) + 1);
});

/*
|--------------------------------------------------------------------------
| Payment recording — the part that must never double-credit
|--------------------------------------------------------------------------
*/

it('settles an invoice and activates the plan when payment lands', function (): void {
    $plan = Plan::where('code', 'growth')->sole();
    $invoice = $this->billing->invoiceForPlan($this->tenant, $plan);

    $this->billing->recordPayment($invoice, PaymentResult::success(
        'paystack', 'psk_123', 'ref_123', $invoice->total, $invoice->currency,
    ));

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->plan_id)->toBe($plan->id);
});

it('never credits the same gateway reference twice', function (): void {
    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'starter')->sole());

    $result = PaymentResult::success('paystack', 'psk_dup', 'ref_dup', $invoice->total, $invoice->currency);

    // Providers retry webhooks aggressively and customers refresh callback
    // pages; both must be harmless.
    $first = $this->billing->recordPayment($invoice, $result);
    $second = $this->billing->recordPayment($invoice, $result);

    expect($second->id)->toBe($first->id)
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total);
});

it('leaves an invoice open on a partial payment', function (): void {
    $invoice = $this->billing->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    $this->billing->recordPayment($invoice, PaymentResult::success(
        'manual', 'manual_partial', 'ref_partial', 1_000, $invoice->currency,
    ));

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_OPEN)
        ->and($invoice->fresh()->balance())->toBe($invoice->total - 1_000);
});

/*
|--------------------------------------------------------------------------
| Plan limits
|--------------------------------------------------------------------------
*/

it('distinguishes an unlimited plan from a zero limit', function (): void {
    // Conflating null with 0 is how an Enterprise customer gets locked out.
    $unlimited = Plan::factory()->unlimited()->create();
    $this->billing->activate($this->tenant, $unlimited, 'USD', null);

    expect($this->planGate->limitFor($this->tenant, 'contacts'))->toBeNull()
        ->and($this->planGate->remaining($this->tenant, 'contacts'))->toBeNull()
        ->and($this->planGate->allows($this->tenant, 'contacts', 1_000_000))->toBeTrue();
});

it('blocks a contact that would exceed the plan limit', function (): void {
    $tiny = Plan::factory()->create(['limits' => ['contacts' => 1]]);
    $this->billing->activate($this->tenant, $tiny, 'USD', null);

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->post(route('contacts.store'), ['email' => 'over@example.com'])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(Contact::count())->toBe(1);
});

it('blocks an extra SMTP relay beyond the plan limit', function (): void {
    $tiny = Plan::factory()->create(['limits' => ['smtp_accounts' => 1]]);
    $this->billing->activate($this->tenant, $tiny, 'USD', null);

    SmtpAccount::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->post(route('smtp-accounts.store'), [
        'name' => 'Second', 'host' => 'smtp.example.com', 'port' => 587,
        'from_email' => 'a@example.com', 'from_name' => 'A',
    ])->assertRedirect()->assertSessionHasErrors();

    expect(SmtpAccount::count())->toBe(1);
});

it('accumulates monthly email usage across concurrent workers', function (): void {
    $this->planGate->recordEmailsSent($this->tenant, 500);
    $this->planGate->recordEmailsSent($this->tenant, 300);

    // Chunk workers upsert the same row; they must add, not overwrite.
    expect($this->planGate->usage($this->tenant, 'emails_per_month'))->toBe(800);
});

it('stops sending once the monthly allowance is spent', function (): void {
    $plan = Plan::factory()->create(['limits' => ['emails_per_month' => 100]]);
    $this->billing->activate($this->tenant, $plan, 'USD', null);

    expect($this->planGate->canSend($this->tenant))->toBeTrue();

    $this->planGate->recordEmailsSent($this->tenant, 100);

    expect($this->planGate->canSend($this->tenant))->toBeFalse();
});

it('keeps a past-due workspace sending through the grace window', function (): void {
    // Cutting a paying customer off the hour a card expires is how you lose
    // them; cutting them off forever is how you get a chargeback.
    $subscription = $this->planGate->subscriptionFor($this->tenant)
        ?? $this->billing->startTrial($this->tenant);

    $subscription->forceFill([
        'status' => Subscription::STATUS_PAST_DUE,
        'current_period_end' => now()->subDay(),
    ])->save();

    expect($subscription->fresh()->isUsable())->toBeTrue();

    $subscription->forceFill([
        'current_period_end' => now()->subDays(config('billing.grace_days') + 1),
    ])->save();

    expect($subscription->fresh()->isUsable())->toBeFalse();
});

it('falls back to free-tier limits when a workspace has no subscription', function (): void {
    Subscription::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

    // Never unlimited by accident.
    expect($this->planGate->limitFor($this->tenant, 'contacts'))->toBe(500);
});

/*
|--------------------------------------------------------------------------
| Cancellation and isolation
|--------------------------------------------------------------------------
*/

it('cancels at period end rather than immediately', function (): void {
    $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'paystack');

    $this->post(route('billing.cancel'))->assertRedirect()->assertSessionHas('success');

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($subscription->canceled_at)->not->toBeNull()
        ->and($subscription->cancel_at)->not->toBeNull()
        ->and($subscription->isUsable())->toBeTrue();
});

it('never shows another workspace an invoice', function (): void {
    $victim = User::factory()->create();
    $foreignInvoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $victim->tenant_id,
        'number' => 'EZ-OTHER-00001',
        'status' => Invoice::STATUS_OPEN,
        'currency' => 'USD', 'subtotal' => 1000, 'total' => 1000,
    ]);

    $this->get(route('billing.invoices.show', $foreignInvoice->id))->assertNotFound();
    $this->post(route('billing.checkout.start', $foreignInvoice->id), ['gateway' => 'manual'])->assertNotFound();
});
