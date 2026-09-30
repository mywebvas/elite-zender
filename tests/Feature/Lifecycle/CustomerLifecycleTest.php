<?php

use App\Console\Commands\RunLifecycle;
use App\Lifecycle\LifecycleMessenger;
use App\Models\Invoice;
use App\Models\LifecycleMessage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\InvoiceIssued;
use App\Notifications\Lifecycle\InvoiceReminder;
use App\Notifications\Lifecycle\PaymentFailed;
use App\Notifications\Lifecycle\PaymentReceived;
use App\Notifications\Lifecycle\RenewalReminder;
use App\Notifications\Lifecycle\SubscriptionCancelled;
use App\Notifications\Lifecycle\SuspensionWarning;
use App\Notifications\Lifecycle\TrialEnding;
use App\Notifications\Lifecycle\UsageThresholdReached;
use App\Notifications\Lifecycle\WinBackOffer;
use App\Notifications\Lifecycle\WorkspaceReinstated;
use App\Notifications\Lifecycle\WorkspaceSuspended;
use App\Notifications\Lifecycle\WorkspaceWelcome;
use Illuminate\Support\Facades\Notification;

/**
 * The customer lifecycle, end to end.
 *
 * Before this existed the product sent exactly one kind of email: a customer
 * campaign. Nothing told a new owner what to do first, nothing warned a trial
 * was ending, nothing chased an abandoned checkout, nothing announced a
 * failed card, and nothing said a word before sending was suspended — that
 * last one was documented in docs/README.md as "dunning notifications are
 * logged, not emailed", which is a polite way of saying the customer finds
 * out when their campaigns stop.
 *
 * Two properties matter more than the copy, and both are asserted here:
 * every message reaches someone who can act on it, and no message can ever
 * be sent twice.
 */
beforeEach(function (): void {
    Notification::fake();
    seedPlans();

    $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Ada Lovelace']);
    $this->tenant = $this->owner->tenant;

    $this->growth = Plan::query()->where('code', 'growth')->sole();

    $this->subscribe = function (array $attributes = []) {
        return Subscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            array_merge([
                'plan_id' => $this->growth->id,
                'status' => Subscription::STATUS_ACTIVE,
                'currency' => 'USD',
                'amount' => $this->growth->price_usd,
                'current_period_start' => now()->subMonth(),
                'current_period_end' => now()->addMonth(),
            ], $attributes),
        );
    };
});

/*
|--------------------------------------------------------------------------
| The ledger: exactly once, to the right people
|--------------------------------------------------------------------------
*/

it('sends a lifecycle message once and only once', function (): void {
    $messenger = app(LifecycleMessenger::class);

    $first = $messenger->sendOnce($this->tenant, 'demo:key', fn () => new WorkspaceSuspended);
    $second = $messenger->sendOnce($this->tenant, 'demo:key', fn () => new WorkspaceSuspended);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();

    Notification::assertSentToTimes($this->owner, WorkspaceSuspended::class, 1);

    expect(LifecycleMessage::withoutGlobalScopes()->where('key', 'demo:key')->count())->toBe(1);
});

it('addresses billing mail to people who can act on it', function (): void {
    $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::ADMIN]);
    $viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::VIEWER]);

    app(LifecycleMessenger::class)->sendOnce($this->tenant, 'demo:roles', fn () => new WorkspaceSuspended);

    Notification::assertSentTo($this->owner, WorkspaceSuspended::class);
    Notification::assertSentTo($admin, WorkspaceSuspended::class);
    // A viewer cannot pay an invoice; telling them one is overdue is noise.
    Notification::assertNotSentTo($viewer, WorkspaceSuspended::class);
});

it('still reaches somebody when a workspace has no owner or admin', function (): void {
    $orphan = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $orphan->id, 'role' => Role::VIEWER]);

    app(LifecycleMessenger::class)->sendOnce($orphan, 'demo:orphan', fn () => new WorkspaceSuspended);

    Notification::assertSentTo($viewer, WorkspaceSuspended::class);
});

it('never sends anything when lifecycle messaging is switched off', function (): void {
    config(['platform.lifecycle.enabled' => false]);

    expect(app(LifecycleMessenger::class)->sendOnce($this->tenant, 'demo:off', fn () => new WorkspaceSuspended))
        ->toBeFalse();

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Guest to customer
|--------------------------------------------------------------------------
*/

it('welcomes a brand-new workspace and points it at setup', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect();

    $user = User::withoutGlobalScopes()->firstWhere('email', 'grace@example.test');

    Notification::assertSentTo($user, WorkspaceWelcome::class);
});

it('honours the operator switch that closes public registration', function (): void {
    config(['platform.signups_open' => false]);

    $this->get('/register')->assertForbidden()->assertSee('Signups are paused');

    $this->post('/register', [
        'name' => 'Blocked',
        'email' => 'blocked@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertForbidden();

    expect(User::withoutGlobalScopes()->where('email', 'blocked@example.test')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Trial to paid
|--------------------------------------------------------------------------
*/

it('warns before a trial ends, not after', function (): void {
    ($this->subscribe)([
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(2),
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, TrialEnding::class);
});

it('leaves a trial that is still weeks away alone', function (): void {
    ($this->subscribe)([
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(20),
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, TrialEnding::class);
});

it('emails an invoice the moment one is raised', function (): void {
    app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);

    Notification::assertSentTo($this->owner, InvoiceIssued::class);
});

/*
|--------------------------------------------------------------------------
| Abandoned checkout
|--------------------------------------------------------------------------
*/

it('chases an invoice that was raised and never paid', function (): void {
    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);
    $invoice->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, InvoiceReminder::class);
});

it('does not chase an invoice that has already been paid', function (): void {
    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);
    $invoice->forceFill(['created_at' => now()->subDays(2)])->save();

    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'gateway' => 'paystack',
        'reference' => 'ref_paid',
        'gateway_ref' => 'gw_paid',
        'status' => Payment::STATUS_SUCCEEDED,
        'currency' => $invoice->currency,
        'amount' => $invoice->total,
        'paid_at' => now(),
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, InvoiceReminder::class);
});

it('nudges an unpaid invoice at most twice, ever', function (): void {
    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);
    $invoice->forceFill(['created_at' => now()->subDays(10)])->save();

    // Both nudges are due at once, and the command runs hourly.
    foreach (range(1, 5) as $ignored) {
        $this->artisan(RunLifecycle::class)->assertSuccessful();
    }

    Notification::assertSentToTimes($this->owner, InvoiceReminder::class, 2);
});

/*
|--------------------------------------------------------------------------
| Renewal
|--------------------------------------------------------------------------
*/

it('warns before charging a saved card', function (): void {
    ($this->subscribe)([
        'current_period_end' => now()->addDays(2),
        'gateway' => 'paystack',
        'gateway_token' => 'AUTH_reusable',
        'card_brand' => 'visa',
        'card_last_four' => '4081',
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, RenewalReminder::class);
});

it('does not promise a charge it cannot make', function (): void {
    // No card on file: an "we will charge your card" email would be a lie
    // that arrives every month.
    ($this->subscribe)(['current_period_end' => now()->addDays(2), 'gateway_token' => null]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, RenewalReminder::class);
});

it('tells the customer when a renewal charge is declined', function (): void {
    $subscription = ($this->subscribe)(['gateway' => 'paystack']);
    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);

    app(App\Billing\BillingService::class)
        ->recordDunningFailure($subscription, $invoice, 'Card expired');

    Notification::assertSentTo($this->owner, PaymentFailed::class);
});

it('sends a receipt when money lands', function (): void {
    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);

    app(App\Billing\BillingService::class)->recordPayment($invoice, App\Billing\PaymentResult::success(
        gateway: 'paystack',
        gatewayRef: 'gw_receipt',
        reference: 'ref_receipt',
        amount: $invoice->total,
        currency: $invoice->currency,
    ));

    Notification::assertSentTo($this->owner, PaymentReceived::class);
});

/*
|--------------------------------------------------------------------------
| Suspend and unsuspend
|--------------------------------------------------------------------------
*/

it('warns before sending is paused, with the exact date', function (): void {
    ($this->subscribe)([
        'status' => Subscription::STATUS_PAST_DUE,
        'current_period_end' => now()->subDays((int) config('billing.grace_days') - 1),
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, SuspensionWarning::class);
});

it('announces the suspension itself', function (): void {
    $subscription = ($this->subscribe)(['status' => Subscription::STATUS_PAST_DUE]);

    app(App\Billing\BillingService::class)->lapse($subscription);

    Notification::assertSentTo($this->owner, WorkspaceSuspended::class);
});

it('confirms reinstatement the moment a suspended workspace pays', function (): void {
    ($this->subscribe)(['status' => Subscription::STATUS_EXPIRED]);

    $invoice = app(App\Billing\BillingService::class)->invoiceForPlan($this->tenant, $this->growth);

    app(App\Billing\BillingService::class)->recordPayment($invoice, App\Billing\PaymentResult::success(
        gateway: 'paystack',
        gatewayRef: 'gw_back',
        reference: 'ref_back',
        amount: $invoice->total,
        currency: $invoice->currency,
    ));

    Notification::assertSentTo($this->owner, WorkspaceReinstated::class);
});

it('shows a suspended workspace a way out rather than a bare 403', function (): void {
    $this->tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertForbidden()
        ->assertSee('Nothing has been deleted')
        ->assertSee(config('platform.support_email'));
});

/*
|--------------------------------------------------------------------------
| Retention
|--------------------------------------------------------------------------
*/

it('records why a customer cancelled and confirms it in writing', function (): void {
    ($this->subscribe)();

    $this->actingAs($this->owner)
        ->post(route('billing.cancel'), [
            'reason' => 'too_expensive',
            'feedback' => 'Great tool, wrong budget this quarter.',
        ])
        ->assertRedirect();

    $subscription = Subscription::withoutGlobalScopes()->firstWhere('tenant_id', $this->tenant->id);

    expect($subscription->cancellation_reason)->toBe('too_expensive')
        ->and($subscription->cancellation_feedback)->toContain('wrong budget');

    Notification::assertSentTo($this->owner, SubscriptionCancelled::class);
});

it('refuses a cancellation reason that is not in the vocabulary', function (): void {
    ($this->subscribe)();

    $this->actingAs($this->owner)
        ->post(route('billing.cancel'), ['reason' => 'because'])
        ->assertSessionHasErrors('reason');
});

it('checks in once, a week after a workspace drops to free', function (): void {
    ($this->subscribe)(['downgraded_at' => now()->subDays(8)]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();
    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentToTimes($this->owner, WinBackOffer::class, 1);
});

/*
|--------------------------------------------------------------------------
| Usage and upsell
|--------------------------------------------------------------------------
*/

it('warns at 80% of the monthly allowance instead of blocking at 100%', function (): void {
    $subscription = ($this->subscribe)();
    $limit = $subscription->plan->limit('emails_per_month');

    app(App\Billing\PlanGate::class)->recordEmailsSent($this->tenant, (int) ($limit * 0.85));

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, UsageThresholdReached::class);
});

it('says nothing while a workspace is well inside its allowance', function (): void {
    ($this->subscribe)();

    app(App\Billing\PlanGate::class)->recordEmailsSent($this->tenant, 10);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, UsageThresholdReached::class);
});

it('never alerts a workspace on an unlimited plan', function (): void {
    $unlimited = Plan::factory()->create([
        'code' => 'unlimited-test',
        'limits' => ['emails_per_month' => null, 'contacts' => null, 'smtp_accounts' => null, 'users' => null],
    ]);

    ($this->subscribe)(['plan_id' => $unlimited->id]);

    app(App\Billing\PlanGate::class)->recordEmailsSent($this->tenant, 1_000_000);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, UsageThresholdReached::class);
});

/*
|--------------------------------------------------------------------------
| Dry run
|--------------------------------------------------------------------------
*/

it('reports what it would send without sending or claiming anything', function (): void {
    ($this->subscribe)([
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->artisan(RunLifecycle::class, ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();

    expect(LifecycleMessage::withoutGlobalScopes()->count())->toBe(0);

    // And a real run afterwards still sends: the dry run must not consume it.
    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, TrialEnding::class);
});
