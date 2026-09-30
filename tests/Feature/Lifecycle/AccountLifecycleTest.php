<?php

use App\Console\Commands\ProcessDataRequests;
use App\Console\Commands\RunLifecycle;
use App\Jobs\BuildWorkspaceExportJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DataRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SuppressionEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\DataExportReady;
use App\Notifications\Lifecycle\PlanLimitsExceeded;
use App\Notifications\Lifecycle\WorkspaceDeletionScheduled;
use App\Notifications\Security\SecurityAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The stages nobody had built: account security, life above the plan
 * ceiling, and leaving.
 *
 *  - **Security.** Changing the email, changing the password and switching
 *    off two-factor all happened in total silence. That is the exact recipe
 *    for account takeover: get a session, change the address, reset the
 *    password to one you control. Every step is a legitimate action by an
 *    authenticated user, so nothing else in the stack objects.
 *  - **Over the ceiling.** A downgrade leaves a workspace holding more than
 *    the new plan allows. Nothing deleted it (correctly) and nothing
 *    mentioned it (incorrectly), so customers met the limit as a silent
 *    refusal months later.
 *  - **Leaving.** GDPR Art. 15 and 17 were an email to an operator, which
 *    is a favour rather than a right — and a product that makes leaving
 *    hard is one people are wary of joining.
 */
beforeEach(function (): void {
    Notification::fake();
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| Account security
|--------------------------------------------------------------------------
*/

it('warns the old address when the account email is changed', function (): void {
    $user = actingAsTenantUser(['email' => 'owner@example.test', 'name' => 'Ada Lovelace']);

    $this->put(route('user-profile-information.update'), [
        'name' => 'Ada Lovelace',
        'email' => 'attacker@example.test',
    ])->assertSessionHasNoErrors();

    // The whole point: the message goes to the address being taken away,
    // because that is the only inbox the real owner still controls.
    Notification::assertSentOnDemand(
        SecurityAlert::class,
        fn (SecurityAlert $n, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'owner@example.test',
    );

    expect($user->fresh()->email)->toBe('attacker@example.test')
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('confirms a password change to the account inbox', function (): void {
    $user = actingAsTenantUser();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'correct-horse-battery-1',
        'password_confirmation' => 'correct-horse-battery-1',
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, SecurityAlert::class);
});

it('tells the owner when two-factor is switched off', function (): void {
    $user = actingAsTenantUser();

    event(new Laravel\Fortify\Events\TwoFactorAuthenticationDisabled($user));

    // Removing 2FA is the most valuable thing an attacker does after taking
    // a session — it stops the control that would lock them out later.
    Notification::assertSentTo($user, SecurityAlert::class);
});

it('never lets a security notice be switched off', function (): void {
    $user = User::factory()->create(['notification_preferences' => ['security' => false, 'product' => false]]);

    expect((new SecurityAlert('x', 'y'))->via($user))->toBe(['mail']);
});

/*
|--------------------------------------------------------------------------
| Above the plan ceiling
|--------------------------------------------------------------------------
*/

function downgradedWorkspace(): Subscription
{
    $owner = User::factory()->create(['role' => Role::OWNER]);
    test()->owner = $owner;

    // Three members on a plan that includes one seat, which is exactly what
    // a Growth-to-Free downgrade produces.
    User::factory()->count(2)->create(['tenant_id' => $owner->tenant_id, 'role' => Role::MEMBER]);

    return Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $owner->tenant_id,
        'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => 0,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
}

it('reports exactly where a workspace sits above its plan', function (): void {
    $subscription = downgradedWorkspace();

    $overages = app(App\Billing\PlanGate::class)->overages($this->owner->tenant);

    expect($overages)->toHaveKey('users')
        ->and($overages['users']['used'])->toBe(3)
        ->and($overages['users']['limit'])->toBe(1)
        ->and($overages['users']['over'])->toBe(2);

    unset($subscription);
});

it('never treats an unlimited plan as an overage', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);

    Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $owner->tenant_id,
        'plan_id' => Plan::query()->where('code', 'enterprise')->sole()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => 0,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    expect(app(App\Billing\PlanGate::class)->overages($owner->tenant))->toBe([]);
});

it('tells a workspace it is above the plan instead of silently refusing later', function (): void {
    downgradedWorkspace();

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    Notification::assertSentTo($this->owner, PlanLimitsExceeded::class);
});

it('says so on every page, naming the overage', function (): void {
    downgradedWorkspace();

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('above the Free plan limits')
        ->assertSee('2 team members over');
});

it('does not delete anything to make a workspace fit', function (): void {
    downgradedWorkspace();

    $this->artisan(RunLifecycle::class)->assertSuccessful();

    // Silently destroying a customer's data to fit a cheaper plan is the one
    // unforgivable behaviour here.
    expect(User::withoutGlobalScopes()->where('tenant_id', $this->owner->tenant_id)->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Leaving: right of access
|--------------------------------------------------------------------------
*/

it('builds a downloadable archive of everything in the workspace', function (): void {
    Storage::fake('local');

    $owner = actingAsTenantUser(['role' => Role::OWNER]);

    Contact::factory()->count(3)->create(['tenant_id' => $owner->tenant_id, 'first_name' => 'Zoë']);
    Campaign::factory()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Spring launch']);

    $this->post(route('data.export'))->assertRedirect();

    $request = DataRequest::withoutGlobalScopes()->where('type', DataRequest::TYPE_EXPORT)->sole();

    expect($request->status)->toBe(DataRequest::STATUS_READY)
        ->and($request->isDownloadable())->toBeTrue();

    // Open it and check it is real, not an empty shell.
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path((string) $request->file_path));

    $names = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    expect($names)->toContain('contacts.csv', 'campaigns.csv', 'team.csv', 'workspace.json', 'README.txt')
        ->and($zip->getFromName('campaigns.csv'))->toContain('Spring launch')
        // UTF-8 BOM so Excel does not render the customer's own data as mojibake.
        ->and(substr((string) $zip->getFromName('contacts.csv'), 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($zip->getFromName('contacts.csv'))->toContain('Zoë');

    $zip->close();

    Notification::assertSentTo($owner, DataExportReady::class);
});

it('keeps relay passwords out of the export', function (): void {
    Storage::fake('local');

    $owner = actingAsTenantUser(['role' => Role::OWNER]);

    App\Models\SmtpAccount::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'password' => 'must-never-travel',
    ]);

    $this->post(route('data.export'));

    $request = DataRequest::withoutGlobalScopes()->sole();
    $contents = file_get_contents(Storage::disk('local')->path((string) $request->file_path));

    // An export travels — mail server, downloads folder, laptop. A bearer
    // credential against a customer's sending domain has no business in one.
    expect($contents)->not->toContain('must-never-travel');
});

it('serves an export only to its own workspace', function (): void {
    Storage::fake('local');

    $owner = actingAsTenantUser(['role' => Role::OWNER]);
    $this->post(route('data.export'));
    $request = DataRequest::withoutGlobalScopes()->sole();

    $this->get(route('data.download', $request))->assertOk();

    // Somebody else's export is not merely forbidden, it does not exist.
    actingAsTenantUser(['role' => Role::OWNER]);
    $this->get(route('data.download', $request))->assertNotFound();

    unset($owner);
});

it('keeps exports away from ordinary members', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->post(route('data.export'))->assertForbidden();
});

it('does not queue a second build while one is running', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    actingAsTenantUser(['role' => Role::OWNER]);

    $this->post(route('data.export'));
    $this->post(route('data.export'));

    expect(DataRequest::withoutGlobalScopes()->count())->toBe(1);

    Illuminate\Support\Facades\Queue::assertPushed(BuildWorkspaceExportJob::class, 1);
});

/*
|--------------------------------------------------------------------------
| Leaving: right to erasure
|--------------------------------------------------------------------------
*/

it('schedules deletion rather than doing it on the spot', function (): void {
    $owner = actingAsTenantUser(['role' => Role::OWNER]);
    $tenant = $owner->tenant;

    $this->post(route('data.delete'), [
        'password' => 'password',
        'confirmation' => $tenant->name,
        'reason' => 'switching',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $deletion = DataRequest::withoutGlobalScopes()->where('type', DataRequest::TYPE_DELETION)->sole();

    expect($deletion->status)->toBe(DataRequest::STATUS_PENDING)
        ->and($deletion->scheduled_for->isFuture())->toBeTrue()
        ->and($deletion->reason)->toBe('switching')
        // A cooling-off window turns an angry Friday click into a Monday
        // decision. Nothing is gone yet.
        ->and(Tenant::whereKey($tenant->id)->exists())->toBeTrue();

    Notification::assertSentTo($owner, WorkspaceDeletionScheduled::class);
});

it('demands the password and the exact workspace name', function (): void {
    $owner = actingAsTenantUser(['role' => Role::OWNER]);

    $this->post(route('data.delete'), ['password' => 'wrong', 'confirmation' => $owner->tenant->name])
        ->assertSessionHasErrors('password');

    $this->post(route('data.delete'), ['password' => 'password', 'confirmation' => 'not the name'])
        ->assertSessionHasErrors('confirmation');

    expect(DataRequest::withoutGlobalScopes()->count())->toBe(0);
});

it('keeps deletion to the owner alone', function (): void {
    $admin = actingAsTenantUser(['role' => Role::ADMIN]);

    $this->post(route('data.delete'), ['password' => 'password', 'confirmation' => $admin->tenant->name])
        ->assertForbidden();
});

it('lets the owner call it off', function (): void {
    $owner = actingAsTenantUser(['role' => Role::OWNER]);

    $this->post(route('data.delete'), ['password' => 'password', 'confirmation' => $owner->tenant->name]);
    $deletion = DataRequest::withoutGlobalScopes()->sole();

    $this->delete(route('data.delete.cancel', $deletion))->assertRedirect();

    expect($deletion->fresh()->status)->toBe(DataRequest::STATUS_CANCELLED);

    $this->artisan(ProcessDataRequests::class)->assertSuccessful();

    expect(Tenant::whereKey($owner->tenant_id)->exists())->toBeTrue();
});

it('erases the workspace once the window closes', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);
    $tenantId = $owner->tenant_id;

    Contact::factory()->count(2)->create(['tenant_id' => $tenantId]);
    Campaign::factory()->create(['tenant_id' => $tenantId]);

    DataRequest::withoutGlobalScopes()->create([
        'tenant_id' => $tenantId,
        'requested_by' => $owner->id,
        'type' => DataRequest::TYPE_DELETION,
        'status' => DataRequest::STATUS_PENDING,
        'scheduled_for' => now()->subMinute(),
    ]);

    $this->artisan(ProcessDataRequests::class)->assertSuccessful();

    // forceDelete, not soft delete: an erasure request that leaves the row
    // behind has not erased anything.
    expect(Tenant::withTrashed()->whereKey($tenantId)->exists())->toBeFalse()
        ->and(User::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists())->toBeFalse()
        ->and(Contact::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists())->toBeFalse();
});

it('keeps suppression hashes alive so opt-outs survive erasure', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);
    $tenantId = $owner->tenant_id;

    SuppressionEntry::suppress('gone@example.test', SuppressionEntry::REASON_UNSUBSCRIBE, $tenantId);

    DataRequest::withoutGlobalScopes()->create([
        'tenant_id' => $tenantId,
        'type' => DataRequest::TYPE_DELETION,
        'status' => DataRequest::STATUS_PENDING,
        'scheduled_for' => now()->subMinute(),
    ]);

    $this->artisan(ProcessDataRequests::class)->assertSuccessful();

    // The rows hold one-way hashes and no addresses. They exist because a
    // recipient asked never to be emailed again — a promise made to them,
    // not to the workspace, so erasing the workspace must not quietly
    // re-enable mail to people who opted out.
    expect(SuppressionEntry::suppresses('gone@example.test'))->toBeTrue()
        ->and(Tenant::withTrashed()->whereKey($tenantId)->exists())->toBeFalse();
});

it('shreds an export once it has aged out', function (): void {
    Storage::fake('local');

    $owner = actingAsTenantUser(['role' => Role::OWNER]);
    $this->post(route('data.export'));

    $request = DataRequest::withoutGlobalScopes()->sole();
    $path = (string) $request->file_path;

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $request->forceFill(['expires_at' => now()->subDay()])->save();

    $this->artisan(ProcessDataRequests::class)->assertSuccessful();

    // A zip of somebody's entire contact list should not outlive the reason
    // it was created.
    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($request->fresh()->file_path)->toBeNull()
        ->and($request->fresh()->isDownloadable())->toBeFalse();

    unset($owner);
});

it('reports what it would do without touching anything', function (): void {
    $owner = User::factory()->create(['role' => Role::OWNER]);

    DataRequest::withoutGlobalScopes()->create([
        'tenant_id' => $owner->tenant_id,
        'type' => DataRequest::TYPE_DELETION,
        'status' => DataRequest::STATUS_PENDING,
        'scheduled_for' => now()->subMinute(),
    ]);

    $this->artisan(ProcessDataRequests::class, ['--dry-run' => true])->assertSuccessful();

    expect(Tenant::whereKey($owner->tenant_id)->exists())->toBeTrue();
});
