<?php

use App\Billing\BillingService;
use App\Billing\PaymentResult;
use App\Billing\PlanGate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * The subscription lifecycle: trials, renewals, dunning, lapses, cancellation,
 * resumption and mid-cycle plan changes.
 *
 * These are the paths a customer travels without ever talking to us, so every
 * one of them has to be correct *and* forgiving.
 */
beforeEach(function (): void {
    seedPlans();

    config([
        'billing.gateways.paystack.secret_key' => 'sk_test_secret',
        'billing.gateways.stripe.secret_key' => 'sk_test_stripe',
        'billing.gateways.stripe.webhook_secret' => 'whsec_test',
    ]);

    $this->user = actingAsTenantUser(['role' => Role::OWNER]);
    $this->tenant = $this->user->tenant;
    $this->billing = app(BillingService::class);
    $this->planGate = app(PlanGate::class);

    // Factory-made tenants are fixtures; only registration calls startTrial.
    // Give every lifecycle test the baseline a real workspace would have.
    $this->billing->startTrial($this->tenant);

    $this->growth = Plan::where('code', 'growth')->sole();
    $this->starter = Plan::where('code', 'starter')->sole();

    $this->subscribeTo = function (Plan $plan, array $overrides = []): Subscription {
        $subscription = $this->billing->activate($this->tenant, $plan, 'USD', 'paystack');
        $subscription->forceFill($overrides)->save();

        return $subscription->fresh();
    };
});

/*
|--------------------------------------------------------------------------
| Trials
|--------------------------------------------------------------------------
*/

it('starts every new workspace on a trial at registration', function (): void {
    auth()->logout();

    $this->post('/register', [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'compiler-1952-nanoseconds',
        'password_confirmation' => 'compiler-1952-nanoseconds',
    ])->assertRedirect('/dashboard');

    $tenant = App\Models\User::where('email', 'grace@example.com')->sole()->tenant;
    $subscription = $this->planGate->subscriptionFor($tenant);

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(Subscription::STATUS_TRIALING)
        ->and($subscription->onTrial())->toBeTrue();
});

it('converts a free trial into an active free plan rather than locking anyone out', function (): void {
    $subscription = $this->planGate->subscriptionFor($this->tenant);
    $subscription->forceFill(['trial_ends_at' => now()->subDay()])->save();

    Artisan::call('elitesender:billing-cycle');

    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($this->planGate->canSend($this->tenant))->toBeTrue();
});

it('invoices a paid trial when it ends and keeps sending during the grace window', function (): void {
    $subscription = ($this->subscribeTo)($this->growth, [
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->subDay(),
    ]);

    Artisan::call('elitesender:billing-cycle');

    $invoice = Invoice::withoutGlobalScopes()->where('reason', Invoice::REASON_RENEWAL)->sole();

    expect($invoice->total)->toBe(5_900)
        ->and($subscription->fresh()->status)->toBe(Subscription::STATUS_PAST_DUE)
        // Past due is not cut off: the customer has the grace window to pay.
        ->and($this->planGate->canSend($this->tenant))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Renewal
|--------------------------------------------------------------------------
*/

it('renews automatically when a card is on file', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::response([
        'status' => true,
        'data' => ['id' => 777, 'status' => 'success', 'amount' => 5_900, 'currency' => 'USD'],
    ])]);

    $subscription = ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'gateway_token' => 'AUTH_reusable',
        'card_brand' => 'visa',
        'card_last_four' => '4242',
    ]);

    Artisan::call('elitesender:billing-cycle');

    $subscription = $subscription->fresh();

    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->current_period_end->isFuture())->toBeTrue()
        ->and(Invoice::withoutGlobalScopes()->sole()->status)->toBe(Invoice::STATUS_PAID);
});

it('anchors the new period to the old period end, not to the run time', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::response([
        'status' => true,
        'data' => ['id' => 778, 'status' => 'success', 'amount' => 5_900, 'currency' => 'USD'],
    ])]);

    $periodEnd = now()->subDays(2)->startOfDay();

    ($this->subscribeTo)($this->growth, [
        'current_period_end' => $periodEnd,
        'gateway_token' => 'AUTH_reusable',
    ]);

    Artisan::call('elitesender:billing-cycle');

    // Renewing two days late must not quietly move the customer's billing date.
    expect($this->planGate->subscriptionFor($this->tenant)->current_period_end->toDateString())
        ->toBe($periodEnd->copy()->addMonth()->toDateString());
});

it('raises an invoice instead of charging when the customer pays by transfer', function (): void {
    $subscription = ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'gateway' => 'manual',
        'gateway_token' => null,
    ]);

    Artisan::call('elitesender:billing-cycle');

    expect(Invoice::withoutGlobalScopes()->where('reason', Invoice::REASON_RENEWAL)->count())->toBe(1)
        ->and($subscription->fresh()->status)->toBe(Subscription::STATUS_PAST_DUE)
        // Offline payers are first-class: they keep working while they pay.
        ->and($this->planGate->canSend($this->tenant))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Dunning
|--------------------------------------------------------------------------
*/

it('retries a declined card on a widening schedule instead of hammering it', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::response([
        'status' => true,
        'data' => ['status' => 'failed', 'gateway_response' => 'Insufficient funds'],
    ])]);

    $subscription = ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'gateway_token' => 'AUTH_declines',
    ]);

    Artisan::call('elitesender:billing-cycle');

    $subscription = $subscription->fresh();

    expect($subscription->status)->toBe(Subscription::STATUS_PAST_DUE)
        ->and($subscription->dunning_attempts)->toBe(1)
        // One day, then three, then five: an immediate retry on a declined card
        // declines again and costs the customer another bank alert.
        ->and($subscription->next_retry_at->isAfter(now()->addHours(20)))->toBeTrue();

    $this->travel(2)->days();
    Artisan::call('elitesender:billing-cycle');

    expect($subscription->fresh()->dunning_attempts)->toBe(2);
});

it('records why a charge failed so support can answer the question', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::response([
        'status' => true,
        'data' => ['status' => 'failed', 'gateway_response' => 'Card expired'],
    ])]);

    ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'gateway_token' => 'AUTH_expired',
    ]);

    Artisan::call('elitesender:billing-cycle');

    expect(Payment::withoutGlobalScopes()->where('status', Payment::STATUS_FAILED)->sole()->failure_reason)
        ->toBe('Card expired');
});

it('recovers the moment a retry succeeds', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::sequence()
        ->push(['status' => true, 'data' => ['status' => 'failed', 'gateway_response' => 'Declined']])
        ->push(['status' => true, 'data' => ['id' => 900, 'status' => 'success', 'amount' => 5_900, 'currency' => 'USD']]),
    ]);

    $subscription = ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'gateway_token' => 'AUTH_flaky',
    ]);

    Artisan::call('elitesender:billing-cycle');
    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_PAST_DUE);

    $this->travel(2)->days();
    Artisan::call('elitesender:billing-cycle');

    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->fresh()->dunning_attempts)->toBe(0);
});

it('lapses only after the grace window, and never deletes anything', function (): void {
    $subscription = ($this->subscribeTo)($this->growth, [
        'status' => Subscription::STATUS_PAST_DUE,
        'current_period_end' => now()->subDays(config('billing.grace_days') + 2),
        'next_retry_at' => null,
    ]);

    Artisan::call('elitesender:billing-cycle');

    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_EXPIRED)
        ->and($this->planGate->canSend($this->tenant))->toBeFalse()
        // Suspension stops sending, not access to your own data.
        ->and($this->get(route('dashboard'))->getStatusCode())->toBe(200)
        ->and($this->get(route('contacts.index'))->getStatusCode())->toBe(200);
});

it('explains in plain words why sending stopped', function (): void {
    ($this->subscribeTo)($this->growth, [
        'status' => Subscription::STATUS_EXPIRED,
        'current_period_end' => now()->subMonth(),
    ]);

    $reason = $this->planGate->sendBlockReason($this->tenant);

    expect($reason)->toContain('Sending is paused')
        ->and($reason)->toContain('billing page');
});

/*
|--------------------------------------------------------------------------
| Cancel and resume
|--------------------------------------------------------------------------
*/

it('cancels at period end and can be undone in one click', function (): void {
    ($this->subscribeTo)($this->growth, ['current_period_end' => now()->addDays(20)]);

    $this->post(route('billing.cancel'))->assertRedirect()->assertSessionHas('success');

    $subscription = $this->planGate->subscriptionFor($this->tenant);
    expect($subscription->isEnding())->toBeTrue()
        // Still fully usable: they paid for this period.
        ->and($subscription->isUsable())->toBeTrue();

    $this->post(route('billing.resume'))->assertRedirect()->assertSessionHas('success');

    $subscription = $this->planGate->subscriptionFor($this->tenant);
    expect($subscription->isEnding())->toBeFalse()
        ->and($subscription->canceled_at)->toBeNull();
});

it('drops to the free plan when the cancelled period actually ends', function (): void {
    ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->subHour(),
        'canceled_at' => now()->subDay(),
        'cancel_at' => now()->subHour(),
    ]);

    Artisan::call('elitesender:billing-cycle');

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($subscription->plan->code)->toBe('free')
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        // No invoice for a period nobody asked for.
        ->and(Invoice::withoutGlobalScopes()->count())->toBe(0);
});

it('removes a saved card on request', function (): void {
    ($this->subscribeTo)($this->growth, ['gateway_token' => 'AUTH_x', 'card_last_four' => '4242']);

    $this->delete(route('billing.card.forget'))->assertRedirect()->assertSessionHas('success');

    expect($this->planGate->subscriptionFor($this->tenant)->gateway_token)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Plan changes
|--------------------------------------------------------------------------
*/

it('prorates an upgrade to the unused part of the period', function (): void {
    ($this->subscribeTo)($this->starter, [
        'amount' => 1_500,
        'current_period_start' => now()->subDays(15),
        'current_period_end' => now()->addDays(15),
    ]);

    $this->post(route('billing.subscribe'), ['plan' => 'growth'])->assertRedirect();

    $invoice = Invoice::withoutGlobalScopes()->where('reason', Invoice::REASON_UPGRADE)->sole();

    // Half a month of the $44.00 difference, rounded in the customer's favour.
    expect($invoice->total)->toBeLessThan(5_900)
        ->and($invoice->total)->toBeGreaterThan(1_500)
        ->and($invoice->line_items[0]['description'])->toContain('prorated');
});

it('schedules a downgrade for period end rather than taking capacity away', function (): void {
    ($this->subscribeTo)($this->growth, [
        'amount' => 5_900,
        'current_period_end' => now()->addDays(20),
    ]);

    $this->post(route('billing.subscribe'), ['plan' => 'starter'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($subscription->pending_plan_id)->toBe($this->starter->id)
        // Nothing changes yet — the capacity is already paid for.
        ->and($subscription->plan_id)->toBe($this->growth->id)
        ->and(Invoice::withoutGlobalScopes()->count())->toBe(0);
});

it('applies a scheduled downgrade at renewal', function (): void {
    Http::fake(['*/transaction/charge_authorization' => Http::response([
        'status' => true,
        'data' => ['id' => 811, 'status' => 'success', 'amount' => 1_500, 'currency' => 'USD'],
    ])]);

    ($this->subscribeTo)($this->growth, [
        'amount' => 5_900,
        'current_period_end' => now()->subHour(),
        'pending_plan_id' => $this->starter->id,
        'gateway_token' => 'AUTH_ok',
    ]);

    Artisan::call('elitesender:billing-cycle');

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($subscription->plan_id)->toBe($this->starter->id)
        ->and($subscription->pending_plan_id)->toBeNull()
        ->and(Invoice::withoutGlobalScopes()->sole()->total)->toBe(1_500);
});

it('lets a customer call off a scheduled downgrade', function (): void {
    ($this->subscribeTo)($this->growth, [
        'current_period_end' => now()->addDays(10),
        'pending_plan_id' => $this->starter->id,
    ]);

    $this->post(route('billing.keep-plan'))->assertRedirect()->assertSessionHas('success');

    expect($this->planGate->subscriptionFor($this->tenant)->pending_plan_id)->toBeNull();
});

it('keeps lifecycle actions away from members', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->post(route('billing.cancel'))->assertForbidden();
    $this->post(route('billing.resume'))->assertForbidden();
    $this->delete(route('billing.card.forget'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Stored credentials
|--------------------------------------------------------------------------
*/

it('remembers a reusable Paystack authorization for the next renewal', function (): void {
    $invoice = $this->billing->invoiceForPlan($this->tenant, $this->growth);

    $this->billing->recordPayment($invoice, PaymentResult::success(
        'paystack', 'psk_1', 'ref_1', $invoice->total, 'USD',
        raw: [
            'authorization' => ['authorization_code' => 'AUTH_keepme', 'reusable' => true, 'brand' => 'visa', 'last4' => '4242'],
            'customer' => ['email' => 'payer@example.com'],
        ],
    ));

    $subscription = $this->planGate->subscriptionFor($this->tenant);

    expect($subscription->gateway_token)->toBe('AUTH_keepme')
        ->and($subscription->card_last_four)->toBe('4242')
        ->and($subscription->canAutoRenew())->toBeTrue();
});

it('ignores a one-time authorization that cannot be reused', function (): void {
    $invoice = $this->billing->invoiceForPlan($this->tenant, $this->growth);

    $this->billing->recordPayment($invoice, PaymentResult::success(
        'paystack', 'psk_2', 'ref_2', $invoice->total, 'USD',
        raw: ['authorization' => ['authorization_code' => 'AUTH_once', 'reusable' => false]],
    ));

    // Storing a non-reusable code would guarantee a failed renewal later.
    expect($this->planGate->subscriptionFor($this->tenant)->gateway_token)->toBeNull();
});

it('never serialises a stored card credential', function (): void {
    $subscription = ($this->subscribeTo)($this->growth, ['gateway_token' => 'AUTH_secret']);

    expect($subscription->toArray())->not->toHaveKey('gateway_token')
        ->and(json_encode($subscription))->not->toContain('AUTH_secret');
});
