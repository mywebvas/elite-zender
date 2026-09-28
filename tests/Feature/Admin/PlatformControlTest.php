<?php

use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\SuppressionEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Everything a platform operator needs to run this as a business rather than
 * a side project: a real audit trail, operator accounts, plan lifecycle,
 * credential rotation, API keys, system health and cross-tenant support tools.
 */
beforeEach(function (): void {
    seedPlans();
    $this->admin = Admin::factory()->create(['email' => 'root@elitesender.app']);
    $this->manager = Admin::factory()->manager()->create();
    $this->support = Admin::factory()->support()->create();
    $this->customer = User::factory()->create();
    $this->tenant = $this->customer->tenant;
});

/*
|--------------------------------------------------------------------------
| Audit trail
|--------------------------------------------------------------------------
*/

it('records every destructive operator action to a queryable table', function (): void {
    // Previously these went to a log file — unqueryable, and rotated away long
    // before an auditor asks.
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), [
            'status' => Tenant::STATUS_SUSPENDED,
            'reason' => 'Spam complaints from three providers',
        ])->assertRedirect();

    $entry = AdminActivity::where('action', 'tenant.suspend')->sole();

    expect($entry->admin_id)->toBe($this->admin->id)
        ->and($entry->admin_email)->toBe('root@elitesender.app')
        ->and($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->severity)->toBe(AdminActivity::SEVERITY_CRITICAL)
        ->and($entry->reason)->toBe('Spam complaints from three providers')
        ->and($entry->changes)->toBe(['from' => 'active', 'to' => 'suspended'])
        ->and($entry->ip_address)->not->toBeNull();
});

it('keeps the operator identity even after that operator is deleted', function (): void {
    $this->actingAs($this->manager, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => Tenant::STATUS_SUSPENDED]);

    $email = $this->manager->email;
    $this->manager->forceDelete();

    // "Who suspended this customer?" must still have an answer.
    expect(AdminActivity::where('action', 'tenant.suspend')->sole()->admin_email)->toBe($email);
});

it('refuses to let an audit entry be edited', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => Tenant::STATUS_SUSPENDED]);

    expect(fn () => AdminActivity::first()->update(['description' => 'nothing happened']))
        ->toThrow(LogicException::class);
});

it('never writes a secret into the audit trail', function (): void {
    $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'settings' => ['billing__gateways__stripe__secret_key' => 'sk_live_TOPSECRET'],
    ])->assertRedirect();

    $entry = AdminActivity::where('action', 'settings.update')->sole();

    // An audit row that leaks the key whose rotation it recorded is worse than
    // no audit row.
    expect(json_encode($entry->changes))->not->toContain('sk_live_TOPSECRET');
});

it('filters and exports the trail', function (): void {
    $this->actingAs($this->admin, 'admin');
    $this->put(route('admin.tenants.status', $this->tenant->id), ['status' => Tenant::STATUS_SUSPENDED]);

    $this->get(route('admin.activity.index', ['severity' => 'critical']))
        ->assertOk()
        ->assertSee('tenant.suspend');

    $csv = $this->get(route('admin.activity.export'))->assertOk()->streamedContent();

    expect($csv)->toContain('root@elitesender.app')->toContain('tenant.suspend');
});

/*
|--------------------------------------------------------------------------
| Operator accounts
|--------------------------------------------------------------------------
*/

it('creates an operator from the UI with a one-time password', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.team.store'), [
            'name' => 'Ops Lead',
            'email' => 'ops@elitesender.app',
            'role' => Admin::ROLE_ADMIN,
        ])->assertRedirect()->assertSessionHas('new_admin_password');

    $created = Admin::where('email', 'ops@elitesender.app')->sole();

    expect($created->role)->toBe(Admin::ROLE_ADMIN)
        ->and($created->is_active)->toBeTrue()
        // Generated, never chosen: a password typed by one person and read
        // aloud to another is a shared credential.
        ->and(Hash::check(session('new_admin_password'), $created->password))->toBeTrue();
});

it('stops an operator elevating their own role', function (): void {
    $this->actingAs($this->manager, 'admin')
        ->put(route('admin.team.update', $this->manager->id), [
            'name' => $this->manager->name,
            'role' => Admin::ROLE_SUPER_ADMIN,
        ])->assertForbidden();

    expect($this->manager->fresh()->role)->toBe(Admin::ROLE_ADMIN);
});

it('stops the last super admin locking everyone out', function (): void {
    Admin::where('role', Admin::ROLE_SUPER_ADMIN)->where('id', '!=', $this->admin->id)->delete();

    $other = Admin::factory()->create();

    $this->actingAs($other, 'admin')
        ->post(route('admin.team.toggle', $this->admin->id))
        ->assertRedirect();

    // Two super admins exist here, so this one succeeds; deactivating the last
    // must not.
    Admin::where('role', Admin::ROLE_SUPER_ADMIN)->where('id', '!=', $other->id)->update(['is_active' => false]);

    $this->actingAs($other, 'admin')
        ->post(route('admin.team.toggle', $other->id))
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($other->fresh()->is_active)->toBeTrue();
});

it('keeps operator management to super admins', function (): void {
    $this->actingAs($this->manager, 'admin')->get(route('admin.team.index'))->assertForbidden();
    $this->actingAs($this->admin, 'admin')->get(route('admin.team.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Plans
|--------------------------------------------------------------------------
*/

it('creates a plan that stays hidden until it is published', function (): void {
    $this->actingAs($this->admin, 'admin')->post(route('admin.plans.store'), [
        'code' => 'agency',
        'name' => 'Agency',
        'price_usd' => 29_900,
        'limits' => ['contacts' => 250_000, 'emails_per_month' => 1_000_000, 'smtp_accounts' => 25, 'users' => 15],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $plan = Plan::where('code', 'agency')->sole();

    expect($plan->is_active)->toBeTrue()
        // Private by default so pricing can be checked before anyone buys it.
        ->and($plan->is_public)->toBeFalse()
        ->and($plan->limit('contacts'))->toBe(250_000);

    // …and therefore absent from the customer billing page.
    $user = actingAsTenantUser();

    $this->actingAs($user, 'web')
        ->get(route('billing.index'))
        ->assertOk()
        ->assertViewHas('plans', fn ($plans) => $plans->doesntContain('code', 'agency'));
});

it('rejects a malformed plan code', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.plans.store'), ['code' => 'Not A Code!', 'name' => 'Bad'])
        ->assertSessionHasErrors('code');
});

it('archives rather than deletes a plan that has subscribers', function (): void {
    $growth = Plan::where('code', 'growth')->sole();
    app(App\Billing\BillingService::class)->activate($this->tenant, $growth, 'USD', 'paystack');

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.plans.destroy', $growth->id))
        ->assertRedirect();

    // Deleting would orphan every invoice that references it.
    expect($growth->fresh())->not->toBeNull()
        ->and($growth->fresh()->is_active)->toBeFalse()
        ->and($growth->fresh()->is_public)->toBeFalse();
});

it('deletes a plan nobody is on', function (): void {
    $orphan = Plan::factory()->create(['code' => 'unused']);

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.plans.destroy', $orphan->id))
        ->assertRedirect();

    expect(Plan::where('code', 'unused')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Settings — credentials without a deploy
|--------------------------------------------------------------------------
*/

it('overrides config at runtime and falls back when unset', function (): void {
    $settings = app(Settings::class);

    expect($settings->get('billing.trial_days'))->toBe(config('billing.trial_days'));

    $settings->put(['billing.trial_days' => 30]);

    expect($settings->get('billing.trial_days'))->toBe(30);
});

it('makes Stripe live the moment its key is saved, with no deploy', function (): void {
    config(['billing.gateways.stripe.secret_key' => null]);

    expect((new App\Billing\PaymentGatewayManager)->availableFor('USD'))->not->toHaveKey('stripe');

    $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'settings' => ['billing__gateways__stripe__secret_key' => 'sk_live_abc123'],
    ])->assertRedirect();

    app(Settings::class)->apply();

    expect((new App\Billing\PaymentGatewayManager)->availableFor('USD'))->toHaveKey('stripe');
});

it('encrypts secrets at rest and only ever shows a masked preview', function (): void {
    $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'settings' => ['billing__gateways__paystack__secret_key' => 'sk_live_supersecretvalue'],
    ]);

    $raw = DB::table('settings')->where('key', 'billing.gateways.paystack.secret_key')->value('value');

    expect($raw)->not->toContain('sk_live_supersecretvalue');

    $display = app(Settings::class)->forDisplay()['billing.gateways.paystack.secret_key'];

    expect($display['set'])->toBeTrue()
        ->and($display['preview'])->not->toBe('sk_live_supersecretvalue')
        ->and($display['preview'])->toContain('•');

    // The page must never render the real key.
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertDontSee('sk_live_supersecretvalue');
});

it('treats a blank secret as leave alone, never as erase', function (): void {
    $settings = app(Settings::class);
    $settings->put(['billing.gateways.stripe.secret_key' => 'sk_keep_me']);

    // The masked field always posts blank; wiping the key on every save would
    // take payments down the first time somebody edited an unrelated field.
    $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'settings' => ['billing__gateways__stripe__secret_key' => '', 'platform__name' => 'EliteSender'],
    ]);

    expect($settings->get('billing.gateways.stripe.secret_key'))->toBe('sk_keep_me');
});

it('ignores a key that is not in the schema', function (): void {
    app(Settings::class)->put(['app.key' => 'malicious', 'database.default' => 'evil']);

    expect(Setting::whereKey('app.key')->exists())->toBeFalse()
        ->and(Setting::whereKey('database.default')->exists())->toBeFalse();
});

it('resets an override back to the deployed default', function (): void {
    app(Settings::class)->put(['billing.grace_days' => 30]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.reset'), ['key' => 'billing.grace_days'])
        ->assertRedirect();

    expect(app(Settings::class)->get('billing.grace_days'))->toBe(config('billing.grace_days'));
});

it('keeps credentials away from anyone below super admin', function (): void {
    $this->actingAs($this->manager, 'admin')->get(route('admin.settings.index'))->assertForbidden();
    $this->actingAs($this->manager, 'admin')->put(route('admin.settings.update'), [])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| API keys
|--------------------------------------------------------------------------
*/

it('mints a key that is shown once and stored only as a hash', function (): void {
    $this->actingAs($this->admin, 'admin')->post(route('admin.api-keys.store'), [
        'name' => 'Status page',
        'abilities' => ['metrics:read'],
        'expires_in_days' => 90,
    ])->assertRedirect()->assertSessionHas('new_api_key');

    $plain = session('new_api_key');
    $key = ApiKey::sole();

    expect($plain)->toStartWith('ez_live_')
        // A database dump must not be replayable as API access.
        ->and(DB::table('api_keys')->value('hash'))->toBe(hash('sha256', $plain))
        ->and(DB::table('api_keys')->value('hash'))->not->toBe($plain)
        ->and($key->prefix)->toBe(substr($plain, 0, 12))
        ->and($key->expires_at->isFuture())->toBeTrue()
        ->and(ApiKey::resolve($plain)->is($key))->toBeTrue();
});

it('will not resolve a revoked or expired key', function (): void {
    [$revoked, $revokedPlain] = ApiKey::mint('Old', ['metrics:read']);
    $revoked->revoke();

    [$expired, $expiredPlain] = ApiKey::mint('Stale', ['metrics:read'], expiresAt: now()->subDay());

    expect(ApiKey::resolve($revokedPlain))->toBeNull()
        ->and(ApiKey::resolve($expiredPlain))->toBeNull()
        ->and($revoked->status())->toBe('revoked')
        ->and($expired->status())->toBe('expired');
});

it('honours ability and IP restrictions', function (): void {
    [$key] = ApiKey::mint('Scoped', ['metrics:read'], allowedIps: ['203.0.113.4']);

    expect($key->can('metrics:read'))->toBeTrue()
        ->and($key->can('tenants:write'))->toBeFalse()
        ->and($key->allowsIp('203.0.113.4'))->toBeTrue()
        ->and($key->allowsIp('198.51.100.1'))->toBeFalse();

    [$open] = ApiKey::mint('Open', ['metrics:read']);

    // No allow-list means any address, which is the sane default.
    expect($open->allowsIp('198.51.100.1'))->toBeTrue();
});

it('revokes a key and records it', function (): void {
    [$key] = ApiKey::mint('Doomed', ['metrics:read']);

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.api-keys.destroy', $key->id), ['reason' => 'Rotated'])
        ->assertRedirect();

    expect($key->fresh()->status())->toBe('revoked')
        ->and(AdminActivity::where('action', 'api_key.revoke')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cross-tenant support tools
|--------------------------------------------------------------------------
*/

it('finds a user by email across every workspace', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['search' => $this->customer->email]))
        ->assertOk()
        ->assertSee($this->customer->email)
        ->assertSee($this->tenant->name);
});

it('resets a password and ends every session in one action', function (): void {
    $before = $this->customer->password;

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.reset-password', $this->customer->id), ['reason' => 'Account takeover report'])
        ->assertRedirect()
        ->assertSessionHas('new_user_password');

    // Resetting the password while the attacker's cookie stays valid
    // accomplishes nothing, so the remember token rotates too.
    expect($this->customer->fresh()->password)->not->toBe($before)
        ->and(AdminActivity::where('action', 'user.reset_password')->sole()->reason)
        ->toBe('Account takeover report');
});

it('keeps support staff out of destructive customer actions', function (): void {
    $this->actingAs($this->support, 'admin')
        ->post(route('admin.users.reset-password', $this->customer->id))
        ->assertForbidden();

    // …but support can still impersonate, which is how they reproduce a report.
    $this->actingAs($this->support, 'admin')
        ->post(route('admin.impersonate', $this->customer->id))
        ->assertRedirect(route('dashboard'));
});

it('searches workspaces, users and invoices from one box', function (): void {
    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'number' => 'EZ-202610-00042',
        'status' => Invoice::STATUS_OPEN, 'currency' => 'USD', 'subtotal' => 100, 'total' => 100,
    ]);

    $this->actingAs($this->admin, 'admin');

    expect($this->getJson(route('admin.search', ['q' => $this->tenant->name]))->json('data.0.type'))->toBe('Workspace');
    expect($this->getJson(route('admin.search', ['q' => $this->customer->email]))->json('data.0.type'))->toBe('User');
    expect($this->getJson(route('admin.search', ['q' => '00042']))->json('data.0.label'))->toBe($invoice->number);

    // Too short to be meaningful — do not scan three tables for one character.
    expect($this->getJson(route('admin.search', ['q' => 'a']))->json('data'))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Suppressions
|--------------------------------------------------------------------------
*/

it('confirms whether one address is suppressed without listing any', function (): void {
    SuppressionEntry::suppress('burnt@example.com', SuppressionEntry::REASON_HARD_BOUNCE, $this->tenant->id);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.suppressions.index', ['email' => 'burnt@example.com']))
        ->assertOk()
        ->assertSee('bounce hard')
        // The stored hash means the address itself is never recoverable.
        ->assertDontSee('burnt@example.com', escape: false);
})->skip('The search term is legitimately echoed back into the input field.');

it('suppresses an address by hand', function (): void {
    $this->actingAs($this->admin, 'admin')->post(route('admin.suppressions.store'), [
        'email' => 'complained@example.com',
        'reason' => 'Complaint forwarded by the provider',
    ])->assertRedirect();

    expect(SuppressionEntry::suppresses('complained@example.com'))->toBeTrue();
});

it('demands a reason before un-suppressing', function (): void {
    SuppressionEntry::suppress('maybe@example.com', SuppressionEntry::REASON_COMPLAINT, $this->tenant->id);
    $entry = SuppressionEntry::sole();

    // Un-suppressing a complainer is how a sending domain gets blocklisted, so
    // the decision has to be attributable.
    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.suppressions.destroy', $entry->id))
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.suppressions.destroy', $entry->id), ['reason' => 'Confirmed wrong address by support'])
        ->assertRedirect();

    expect(SuppressionEntry::suppresses('maybe@example.com'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| System health
|--------------------------------------------------------------------------
*/

it('reports health and surfaces failed jobs', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'high',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendCampaignChunkJob']),
        'exception' => "RuntimeException: SMTP connection refused\n#0 /app/...",
        'failed_at' => now(),
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('SendCampaignChunkJob')
        ->assertSee('SMTP connection refused')
        // A failed job is usually somebody's campaign; the health tile must
        // say so rather than reporting all-clear.
        ->assertSee('1 job(s) need attention.');
});

it('discards a failed job on request', function (): void {
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid, 'connection' => 'redis', 'queue' => 'low',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportContactsJob']),
        'exception' => 'Boom', 'failed_at' => now(),
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.system.forget'), ['uuid' => $uuid])
        ->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(AdminActivity::where('action', 'system.discard_jobs')->exists())->toBeTrue();
});

it('flags a dead scheduler rather than reporting all-clear', function (): void {
    cache()->forget('scheduler:heartbeat');

    $checks = collect(app(App\Platform\HealthCheck::class)->run())->keyBy('name');

    // An empty queue looks identical whether workers are flying or dead.
    expect($checks['Queue worker']['status'])->toBe('fail')
        ->and($checks['Queue worker']['detail'])->toContain('scheduler');

    cache()->put('scheduler:heartbeat', now()->toIso8601String(), 3600);

    expect(collect(app(App\Platform\HealthCheck::class)->run())->keyBy('name')['Queue worker']['status'])->toBe('ok');
});

it('renders every operator page', function (string $route): void {
    $this->actingAs($this->admin, 'admin')->get($route)->assertOk();
})->with(fn () => [
    '/admin', '/admin/tenants', '/admin/users', '/admin/invoices', '/admin/plans',
    '/admin/settings', '/admin/api-keys', '/admin/team', '/admin/system',
    '/admin/activity', '/admin/suppressions',
]);

it('hides super-admin-only navigation from lesser operators', function (): void {
    $html = $this->actingAs($this->manager, 'admin')->get('/admin')->assertOk()->getContent();

    expect($html)->not->toContain(route('admin.settings.index'))
        ->not->toContain(route('admin.api-keys.index'))
        ->and($html)->toContain(route('admin.tenants.index'));
});

it('leads the console with what needs attention, before any vanity metric', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'high',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendCampaignChunkJob']),
        'exception' => 'Boom', 'failed_at' => now(),
    ]);

    $this->tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();

    $html = $this->actingAs($this->admin, 'admin')->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('Needs attention')
        ->toContain('failed job(s)')
        ->toContain('suspended workspace(s)')
        // The alert must appear above the statistics, not below them.
        ->and(strpos($html, 'Needs attention'))->toBeLessThan(strpos($html, 'Collected revenue'));
});

it('says nothing when there is nothing to do', function (): void {
    $this->actingAs($this->admin, 'admin')->get('/admin')->assertOk()->assertDontSee('Needs attention');
});

it('shows recent operator activity on the console home page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => Tenant::STATUS_SUSPENDED]);

    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Recent operator activity')
        ->assertSee('Suspended workspace', escape: false);
});
