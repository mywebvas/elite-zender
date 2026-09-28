<?php

use App\Billing\BillingService;
use App\Billing\PlanGate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;

/**
 * Billing is the screen where confusion costs money, so the states it can be in
 * are asserted rather than eyeballed.
 */
beforeEach(function (): void {
    seedPlans();
    $this->user = actingAsTenantUser(['role' => Role::OWNER]);
    $this->tenant = $this->user->tenant;
    $this->billing = app(BillingService::class);
    $this->planGate = app(PlanGate::class);
    $this->billing->startTrial($this->tenant);
});

it('offers an undo instead of a dead end once a plan is cancelled', function (): void {
    $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'paystack');
    $this->post(route('billing.cancel'));

    $this->get(route('billing.index'))
        ->assertOk()
        // The primary action on a cancelled plan is coming back, not leaving.
        ->assertSee('Keep my plan')
        ->assertSee('your contacts, campaigns and history all stay put', escape: false);
});

it('shows the saved card so nobody wonders what will be charged', function (): void {
    $subscription = $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'stripe');
    // Brand and last four alone are not a saved card — without the token we
    // cannot actually charge again, and claiming otherwise would be a lie.
    $subscription->forceFill([
        'gateway_token' => 'pm_saved',
        'card_brand' => 'visa',
        'card_last_four' => '4242',
    ])->save();

    $this->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Visa ending 4242')
        ->assertSee('Remove card');
});

it('explains a scheduled downgrade rather than silently changing the plan', function (): void {
    $subscription = $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'paystack');
    $subscription->forceFill([
        'pending_plan_id' => Plan::where('code', 'starter')->value('id'),
        'current_period_end' => now()->addDays(12),
    ])->save();

    $this->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Scheduled: moving to Starter')
        ->assertSee('Cancel scheduled change');
});

it('warns before an allowance runs out, not after', function (): void {
    $plan = Plan::factory()->create(['limits' => ['emails_per_month' => 100, 'contacts' => 10_000, 'smtp_accounts' => 5, 'users' => 5]]);
    $this->billing->activate($this->tenant, $plan, 'USD', null);
    $this->planGate->recordEmailsSent($this->tenant, 95);

    $this->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Nearly full — consider upgrading');
});

it('surfaces a past-due state on every page, not just billing', function (): void {
    $subscription = $this->planGate->subscriptionFor($this->tenant);
    $subscription->forceFill([
        'status' => Subscription::STATUS_PAST_DUE,
        'current_period_end' => now()->subDay(),
    ])->save();

    // One notice, resolved once per request, so it cannot say different things
    // on different screens.
    foreach ([route('dashboard'), route('campaigns.index'), route('contacts.index')] as $url) {
        $this->get($url)->assertOk()->assertSee('could not collect your last payment', escape: false);
    }
});

it('tells a lapsed workspace what to do and keeps their data reachable', function (): void {
    $subscription = $this->planGate->subscriptionFor($this->tenant);
    $subscription->forceFill([
        'status' => Subscription::STATUS_EXPIRED,
        'current_period_end' => now()->subMonth(),
    ])->save();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your data is untouched', escape: false)
        ->assertSee('Go to billing');

    // And the data really is reachable.
    $this->get(route('contacts.index'))->assertOk();
});

it('says nothing at all when everything is fine', function (): void {
    $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'paystack');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Go to billing')
        ->assertDontSee('could not collect');
});

it('labels the action on each plan card by what it will actually do', function (): void {
    // From Growth: Scale is an upgrade, Starter a downgrade, Free a switch.
    $this->billing->activate($this->tenant, Plan::where('code', 'growth')->sole(), 'USD', 'paystack');

    $html = $this->get(route('billing.index'))->assertOk()->getContent();

    expect($html)->toContain('Upgrade now')        // a more expensive plan
        ->toContain('Switch at renewal')            // a cheaper one
        ->toContain('Your current plan');           // the one they are on
});
