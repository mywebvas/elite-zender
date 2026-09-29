<?php

use App\Console\Commands\FinaliseCampaigns;
use App\Console\Commands\RunLifecycle;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmtpAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\ActivationNudge;
use App\Notifications\Lifecycle\CampaignReport;
use App\Notifications\Lifecycle\CardExpiring;
use App\Notifications\Lifecycle\WinBackOffer;
use Illuminate\Support\Facades\Notification;

/**
 * The three leaks that survived the first lifecycle pass.
 *
 *  1. **Stalled activation.** A workspace that signs up and never connects a
 *     relay got one welcome email and then silence forever. It cannot send,
 *     so it will never renew — the single biggest hole in the funnel.
 *  2. **Expiring cards.** Both gateways hand back the expiry on the first
 *     charge and it was thrown away, so involuntary churn — a lapse nobody
 *     chose — was only ever discovered as a decline.
 *  3. **No reason to come back.** Nothing reported on a finished campaign,
 *     in a product whose entire value is measurement.
 *
 * Plus the preference layer that makes the optional half of this opt-out-able
 * without touching the half that must never be.
 */
beforeEach(function (): void {
    Notification::fake();
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| Stalled activation
|--------------------------------------------------------------------------
*/

function stalledWorkspace(int $daysOld, array $userAttributes = []): User
{
    $tenant = Tenant::factory()->create(['created_at' => now()->subDays($daysOld)->subHours(2)]);

    return User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'role' => Role::OWNER,
    ], $userAttributes));
}

it('nudges a workspace that signed up and never connected a relay', function (): void {
    $owner = stalledWorkspace(2);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($owner, ActivationNudge::class);
});

it('says nothing to a workspace that has already connected one', function (): void {
    $owner = stalledWorkspace(2);

    SmtpAccount::factory()->create(['tenant_id' => $owner->tenant_id]);
    Contact::factory()->create(['tenant_id' => $owner->tenant_id]);
    Campaign::factory()->create(['tenant_id' => $owner->tenant_id, 'sent_count' => 1]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($owner, ActivationNudge::class);
});

it('leaves an unverified workspace to the verification banner instead', function (): void {
    // Unverified has its own prompt on every page and its own email; a second
    // nudge saying the same thing is noise.
    $owner = stalledWorkspace(2, ['email_verified_at' => null]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($owner, ActivationNudge::class);
});

it('nudges at most twice and then stops for good', function (): void {
    $owner = stalledWorkspace(2);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    // Six days old now: the second nudge is due.
    $owner->tenant->forceFill(['created_at' => now()->subDays(6)->subHours(2)])->save();
    $this->artisan(RunLifecycle::class)->assertSuccessful();

    // And it runs hourly forever after.
    foreach (range(1, 4) as $ignored) {
        $this->artisan(RunLifecycle::class)->assertSuccessful();
    }

    Notification::assertSentToTimes($owner, ActivationNudge::class, 2);
});

/*
|--------------------------------------------------------------------------
| Expiring cards — involuntary churn
|--------------------------------------------------------------------------
*/

function subscriptionWithCard(array $attributes = []): Subscription
{
    $owner = User::factory()->create(['role' => Role::OWNER]);
    $plan = Plan::query()->where('code', 'growth')->sole();

    $subscription = Subscription::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $owner->tenant_id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => $plan->price_usd,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonth(),
        'gateway' => 'paystack',
        'gateway_token' => 'AUTH_demo',
        'card_brand' => 'visa',
        'card_last_four' => '4081',
    ], $attributes));

    $subscription->setRelation('tenant', $owner->tenant);
    test()->owner = $owner;

    return $subscription;
}

it('warns before the card on file expires', function (): void {
    // Frozen mid-month so "expires at the end of this month" is reliably
    // inside the 14-day window whatever day the suite happens to run.
    $this->travelTo(Illuminate\Support\Carbon::create(2026, 6, 20));

    subscriptionWithCard([
        'card_exp_month' => 6,
        'card_exp_year' => 2026,
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, CardExpiring::class);
});

it('stays quiet about a card that is good for another year', function (): void {
    $this->travelTo(Illuminate\Support\Carbon::create(2026, 6, 20));

    subscriptionWithCard([
        'card_exp_month' => 12,
        'card_exp_year' => 2027,
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, CardExpiring::class);
});

it('treats a card as valid through the last day of its expiry month', function (): void {
    $subscription = subscriptionWithCard([
        'card_exp_month' => now()->month,
        'card_exp_year' => now()->year,
    ]);

    expect($subscription->cardExpiresAt()->isSameDay(now()->endOfMonth()))->toBeTrue()
        ->and($subscription->cardExpiresAt()->isFuture())->toBeTrue();
});

it('warns only once per expiry', function (): void {
    $this->travelTo(Illuminate\Support\Carbon::create(2026, 6, 20));

    subscriptionWithCard([
        'card_exp_month' => 6,
        'card_exp_year' => 2026,
    ]);

    foreach (range(1, 4) as $ignored) {
        $this->artisan(RunLifecycle::class)->assertSuccessful();
    }

    Notification::assertSentToTimes($this->owner, CardExpiring::class, 1);
});

it('never warns about a card that is not on file', function (): void {
    subscriptionWithCard([
        'gateway_token' => null,
        'card_exp_month' => now()->month,
        'card_exp_year' => now()->year,
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertNotSentTo($this->owner, CardExpiring::class);
});

it('clears the stored expiry when the card is removed', function (): void {
    $subscription = subscriptionWithCard([
        'card_exp_month' => 4,
        'card_exp_year' => now()->addYear()->year,
    ]);

    $this->actingAs($this->owner)->delete(route('billing.card.forget'))->assertRedirect();

    $fresh = $subscription->fresh();

    expect($fresh->card_exp_month)->toBeNull()
        ->and($fresh->card_exp_year)->toBeNull()
        ->and($fresh->cardExpiresAt())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Campaign report — the reason to come back
|--------------------------------------------------------------------------
*/

it('reports on a campaign once it has finished delivering', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);

    $campaign = Campaign::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'status' => Campaign::STATUS_SENDING,
        'recipients_count' => 10,
        'sent_count' => 9,
        'failed_count' => 1,
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $owner->tenant_id]);

    foreach ([CampaignEvent::TYPE_OPEN, CampaignEvent::TYPE_OPEN, CampaignEvent::TYPE_CLICK] as $i => $type) {
        CampaignEvent::withoutGlobalScopes()->create([
            'tenant_id' => $owner->tenant_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'type' => $type,
        ]);
    }

    $this->artisan(FinaliseCampaigns::class)->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_COMPLETED);

    Notification::assertSentTo($owner, CampaignReport::class);
});

it('reports on a campaign only once, however often the finaliser runs', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);

    Campaign::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'status' => Campaign::STATUS_SENDING,
        'recipients_count' => 2,
        'sent_count' => 2,
    ]);

    foreach (range(1, 3) as $ignored) {
        $this->artisan(FinaliseCampaigns::class)->assertSuccessful();
    }

    Notification::assertSentToTimes($owner, CampaignReport::class, 1);
});

it('still finalises the campaign when nobody can be reported to', function (): void {
    // A workspace with no reachable recipient. Reporting is a courtesy;
    // marking the campaign complete is the actual job of this command, and
    // it must not depend on the courtesy succeeding.
    $tenant = Tenant::factory()->create();

    $campaign = Campaign::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Campaign::STATUS_SENDING,
        'recipients_count' => 1,
        'sent_count' => 1,
    ]);

    $this->artisan(FinaliseCampaigns::class)->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_COMPLETED)
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Preferences: what can be switched off, and what must never be
|--------------------------------------------------------------------------
*/

it('lets a user switch off the optional mail', function (): void {
    $user = actingAsTenantUser(['role' => Role::OWNER]);

    $this->put(route('settings.notifications'), ['reports' => '1'])->assertRedirect();

    $user->refresh();

    expect($user->wantsNotification('product'))->toBeFalse()
        ->and($user->wantsNotification('reports'))->toBeTrue();
});

it('honours the opt-out when a lifecycle message is sent', function (): void {
    $owner = User::factory()->create([
        'role' => Role::OWNER,
        'notification_preferences' => ['product' => false],
    ]);

    Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $owner->tenant_id,
        'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => 0,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonth(),
        'downgraded_at' => now()->subDays(8),
    ]);

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    // Claimed and suppressed at the channel, which is the honest outcome:
    // we tried, they said no.
    Notification::assertNotSentTo($owner, WinBackOffer::class);
});

it('refuses to let anybody opt out of billing or security mail', function (): void {
    $user = User::factory()->create(['notification_preferences' => ['billing' => false, 'security' => false]]);

    // Only the declared optional categories are honoured; everything else is
    // mandatory by construction rather than by a check somebody can forget.
    expect($user->wantsNotification('billing'))->toBeTrue()
        ->and($user->wantsNotification('security'))->toBeTrue()
        ->and(array_keys(User::OPTIONAL_NOTIFICATIONS))->toBe(['product', 'reports']);
});

/*
|--------------------------------------------------------------------------
| Bounce mailbox — a feature that could not be switched on
|--------------------------------------------------------------------------
*/

it('saves the bounce mailbox the settings form always claimed to save', function (): void {
    // The form had no action and a button that fired
    // `window.$toast('IMAP Settings saved successfully')`. Nothing was ever
    // written, so ScanBounces skipped every workspace on every 15-minute run
    // and the whole Bounce Shield feature was unreachable.
    $user = actingAsTenantUser(['role' => Role::ADMIN]);

    $this->put(route('settings.imap'), [
        'host' => 'imap.example.com',
        'port' => 993,
        'username' => 'bounces@example.com',
        'password' => 'app-password',
        'encryption' => 'ssl',
    ])->assertRedirect();

    $imap = $user->tenant->fresh()->setting('imap');

    expect($imap)->toBeArray()
        ->and($imap['host'])->toBe('imap.example.com')
        ->and($imap['username'])->toBe('bounces@example.com')
        // A credential that reads somebody's inbox is not stored in clear.
        ->and($imap['password'])->not->toBe('app-password')
        ->and(Illuminate\Support\Facades\Crypt::decryptString($imap['password']))->toBe('app-password');
});

it('keeps the stored mailbox password when the field is left blank', function (): void {
    $user = actingAsTenantUser(['role' => Role::ADMIN]);

    $this->put(route('settings.imap'), [
        'host' => 'imap.example.com', 'port' => 993,
        'username' => 'bounces@example.com', 'password' => 'original', 'encryption' => 'ssl',
    ]);

    $this->put(route('settings.imap'), [
        'host' => 'imap2.example.com', 'port' => 993,
        'username' => 'bounces@example.com', 'password' => '', 'encryption' => 'ssl',
    ])->assertRedirect();

    $imap = $user->tenant->fresh()->setting('imap');

    expect($imap['host'])->toBe('imap2.example.com')
        ->and(Illuminate\Support\Facades\Crypt::decryptString($imap['password']))->toBe('original');
});

it('lets a workspace disconnect its bounce mailbox', function (): void {
    $user = actingAsTenantUser(['role' => Role::ADMIN]);

    $this->put(route('settings.imap'), [
        'host' => 'imap.example.com', 'port' => 993,
        'username' => 'b@example.com', 'password' => 'x', 'encryption' => 'ssl',
    ]);

    $this->put(route('settings.imap'), ['disconnect' => '1'])->assertRedirect();

    expect($user->tenant->fresh()->setting('imap'))->toBeNull();
});

it('keeps mailbox credentials away from members', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->put(route('settings.imap'), [
        'host' => 'imap.example.com', 'port' => 993,
        'username' => 'b@example.com', 'password' => 'x', 'encryption' => 'ssl',
    ])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| The two highest-trust emails
|--------------------------------------------------------------------------
*/

it('sends verification and password reset in the product shell', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email_verified_at' => null]);

    $verify = (new Illuminate\Auth\Notifications\VerifyEmail)->toMail($user)->render();
    $reset = (new Illuminate\Auth\Notifications\ResetPassword('token-123'))->toMail($user)->render();

    // Stock Laravel markdown at the exact moment a customer decides whether
    // to trust the product with their data is the one inconsistency that
    // actually costs money — it is also what a phishing page looks like.
    foreach ([$verify, $reset] as $html) {
        expect($html)->toContain(config('platform.name'))
            ->and($html)->toContain(config('platform.support_email'))
            ->and($html)->toContain('Hi Ada,');
    }
});

it('performs a real handshake when a relay is tested', function (): void {
    actingAsTenantUser(['role' => Role::ADMIN]);

    // A host that cannot answer must produce a refusal, not a green tick.
    // The old button was a 1.5s setTimeout and an unconditional
    // "Connection test successful!" — you found out it had lied when your
    // first campaign silently failed.
    $relay = SmtpAccount::factory()->create([
        'tenant_id' => auth()->user()->tenant_id,
        'host' => '127.0.0.1',
        'port' => 1,
        'encryption' => 'none',
        'status' => SmtpAccount::STATUS_ACTIVE,
    ]);

    $this->post(route('smtp-accounts.test', $relay))
        ->assertRedirect()
        ->assertSessionHasErrors('smtp');

    $relay->refresh();

    expect($relay->status)->toBe(SmtpAccount::STATUS_ERROR)
        ->and($relay->last_checked_at)->not->toBeNull();
});
