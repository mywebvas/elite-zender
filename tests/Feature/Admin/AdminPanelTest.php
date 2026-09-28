<?php

use App\Billing\BillingService;
use App\Models\Admin;
use App\Models\AdminImpersonation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    seedPlans();
    $this->admin = Admin::factory()->create(['email' => 'root@elitesender.app']);
    $this->customer = User::factory()->create();
    $this->tenant = $this->customer->tenant;
});

/*
|--------------------------------------------------------------------------
| Guard separation — the whole reason admins live in their own table
|--------------------------------------------------------------------------
*/

it('locks the console behind the admin guard', function (string $route): void {
    $this->get($route)->assertRedirect(route('admin.login'));
})->with(fn () => ['/admin', '/admin/tenants', '/admin/invoices']);

it('does not let a customer session reach the console', function (): void {
    // A tenant user is authenticated on `web`, never on `admin`.
    $this->actingAs($this->customer, 'web')->get('/admin')->assertRedirect(route('admin.login'));
});

it('does not let an admin session reach the customer app', function (): void {
    $this->actingAs($this->admin, 'admin')->get('/dashboard')->assertRedirect(route('login'));
});

it('signs an operator in and records the login', function (): void {
    $this->post(route('admin.login.store'), [
        'email' => 'root@elitesender.app',
        'password' => 'operator-password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->admin, 'admin');
    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('cannot authenticate a deactivated operator', function (): void {
    $this->admin->forceFill(['is_active' => false])->save();

    // The login attempt constrains on is_active, so the provider must not even
    // return the record to be password-checked.
    expect(auth('admin')->getProvider()->retrieveByCredentials([
        'email' => 'root@elitesender.app',
        'is_active' => true,
    ]))->toBeNull();
});

it('cuts off an operator deactivated mid-session', function (): void {
    $active = Admin::factory()->create();

    $this->actingAs($active, 'admin')->get('/admin')->assertOk();

    // Access must end immediately, not whenever the cookie happens to expire.
    $active->forceFill(['is_active' => false])->save();

    $this->actingAs($active, 'admin')->get('/admin')->assertRedirect(route('admin.login'));
});

it('throttles operator logins harder than customer logins', function (): void {
    // Five attempts per IP with a 15-minute penalty, against the customer
    // limiter's five per minute: this login guards every workspace on the
    // platform, not one.
    $key = 'admin-login:127.0.0.1';

    foreach (range(1, 5) as $ignored) {
        Illuminate\Support\Facades\RateLimiter::hit($key, 900);
    }

    expect(Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5))->toBeTrue();

    $this->post(route('admin.login.store'), [
        'email' => 'root@elitesender.app',
        'password' => 'operator-password',
    ])->assertSessionHasErrors('email');

    // Even the correct password is refused while the lockout holds.
    $this->assertGuest('admin');
});

/*
|--------------------------------------------------------------------------
| Cross-tenant visibility — the console's whole purpose
|--------------------------------------------------------------------------
*/

it('sees every workspace, unscoped', function (): void {
    $other = User::factory()->create()->tenant;

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.tenants.index'))
        ->assertOk()
        ->assertSee($this->tenant->name)
        ->assertSee($other->name);
});

it('renders every console page', function (string $route): void {
    $this->actingAs($this->admin, 'admin')->get($route)->assertOk();
})->with(fn () => ['/admin', '/admin/tenants', '/admin/invoices', '/admin/plans']);

it('shows a workspace with its subscription, usage and invoices', function (): void {
    app(BillingService::class)->startTrial($this->tenant);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.tenants.show', $this->tenant->id))
        ->assertOk()
        ->assertSee($this->tenant->name)
        ->assertSee($this->customer->email);
});

/*
|--------------------------------------------------------------------------
| Role separation inside the console
|--------------------------------------------------------------------------
*/

it('keeps pricing and refunds to super admins only', function (): void {
    $manager = Admin::factory()->manager()->create();

    $this->actingAs($manager, 'admin')->get(route('admin.plans.index'))->assertForbidden();
    $this->actingAs($this->admin, 'admin')->get(route('admin.plans.index'))->assertOk();
});

it('keeps customer-data actions away from read-only support', function (): void {
    $support = Admin::factory()->support()->create();

    $this->actingAs($support, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => 'suspended'])
        ->assertForbidden();

    expect($this->tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

it('suspends and reinstates a workspace without deleting anything', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => 'suspended', 'reason' => 'Abuse report'])
        ->assertRedirect();

    expect($this->tenant->fresh()->status)->toBe(Tenant::STATUS_SUSPENDED)
        // Suspension closes the door; it never destroys customer data.
        ->and(User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    // An operator can still reach a suspended workspace — otherwise suspending
    // one while impersonating would strand them with no route back.
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.status', $this->tenant->id), ['status' => 'active'])
        ->assertRedirect();

    expect($this->tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

it('locks the workspace users out while it is suspended', function (): void {
    $this->tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();

    // No operator session here: this is what the customer actually sees.
    $this->actingAs($this->customer, 'web')->get('/dashboard')->assertForbidden();
});

it('moves a workspace onto any plan without charging', function (): void {
    $scale = Plan::where('code', 'scale')->sole();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.tenants.plan', $this->tenant->id), ['plan_id' => $scale->id, 'reason' => 'Agreed deal'])
        ->assertRedirect();

    expect(app(App\Billing\PlanGate::class)->subscriptionFor($this->tenant)->plan_id)->toBe($scale->id)
        ->and(Invoice::withoutGlobalScopes()->count())->toBe(0);
});

it('lets a super admin change pricing', function (): void {
    $plan = Plan::where('code', 'starter')->sole();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.plans.update', $plan->id), [
            'name' => 'Starter', 'price_usd' => 1_900, 'price_ngn' => 1_500_000,
            'limits' => ['contacts' => 8_000, 'emails_per_month' => 40_000, 'smtp_accounts' => 4, 'users' => 4],
            'is_active' => '1', 'is_public' => '1',
        ])->assertRedirect();

    expect($plan->fresh()->price_usd)->toBe(1_900)
        ->and($plan->fresh()->limit('contacts'))->toBe(8_000);
});

it('keeps an explicit zero limit distinct from unlimited', function (): void {
    $plan = Plan::where('code', 'free')->sole();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.plans.update', $plan->id), [
            'name' => 'Free',
            'limits' => ['contacts' => 0, 'emails_per_month' => null, 'smtp_accounts' => 1, 'users' => 1],
        ])->assertRedirect();

    expect($plan->fresh()->limit('contacts'))->toBe(0)          // none allowed
        ->and($plan->fresh()->limit('emails_per_month'))->toBeNull(); // unlimited
});

/*
|--------------------------------------------------------------------------
| Impersonation
|--------------------------------------------------------------------------
*/

it('logs in as a customer, records it and shows a banner', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.impersonate', $this->customer->id), ['reason' => 'Investigating ticket 42'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->customer, 'web');

    $record = AdminImpersonation::sole();

    expect($record->admin_id)->toBe($this->admin->id)
        ->and($record->reason)->toBe('Investigating ticket 42')
        ->and($record->ended_at)->toBeNull();

    // An operator who forgets where they are will eventually send a real
    // campaign from a customer's account.
    $this->get(route('dashboard'))->assertOk()->assertSee('Operator view', escape: false);
});

it('returns to the console and closes the impersonation record', function (): void {
    $this->actingAs($this->admin, 'admin')->post(route('admin.impersonate', $this->customer->id));

    $this->post(route('impersonate.stop'))->assertRedirect(route('admin.dashboard'));

    expect(AdminImpersonation::sole()->ended_at)->not->toBeNull();
    $this->assertGuest('web');
    // The admin guard was never logged out, so the operator is still in.
    $this->assertAuthenticatedAs($this->admin, 'admin');
});

it('will not let a customer impersonate anybody', function (): void {
    $this->actingAs($this->customer)
        ->post(route('admin.impersonate', $this->customer->id))
        ->assertRedirect(route('admin.login'));

    expect(AdminImpersonation::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Manual payment review
|--------------------------------------------------------------------------
*/

it('confirms a bank transfer and settles the invoice', function (): void {
    $invoice = app(BillingService::class)->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    $payment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'gateway' => 'manual',
        'reference' => 'EZABCDEFGH',
        'status' => Payment::STATUS_PENDING,
        'currency' => $invoice->currency,
        'amount' => $invoice->total,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.payments.confirm', $payment->id))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($payment->fresh()->reviewed_by)->toBe($this->admin->id);
});

it('cannot credit the same transfer twice', function (): void {
    $invoice = app(BillingService::class)->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    $payment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id,
        'gateway' => 'manual', 'reference' => 'EZDOUBLE', 'status' => Payment::STATUS_PENDING,
        'currency' => $invoice->currency, 'amount' => $invoice->total,
    ]);

    $this->actingAs($this->admin, 'admin')->post(route('admin.payments.confirm', $payment->id));
    // The second click hits a payment that is no longer awaiting review.
    $this->actingAs($this->admin, 'admin')->post(route('admin.payments.confirm', $payment->id))->assertStatus(422);

    expect($invoice->fresh()->amount_paid)->toBe($invoice->total);
});

it('records a refund as a new negative entry rather than editing history', function (): void {
    $invoice = app(BillingService::class)->invoiceForPlan($this->tenant, Plan::where('code', 'growth')->sole());

    app(BillingService::class)->recordPayment($invoice, App\Billing\PaymentResult::success(
        'paystack', 'psk_refundme', 'ref_r', $invoice->total, $invoice->currency,
    ));

    $payment = Payment::withoutGlobalScopes()->where('gateway_ref', 'psk_refundme')->sole();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.payments.refund', $payment->id), ['amount' => 1_000, 'reason' => 'Goodwill'])
        ->assertRedirect();

    expect(Payment::withoutGlobalScopes()->where('amount', '<', 0)->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED)
        ->and($invoice->fresh()->amount_paid)->toBe($invoice->total - 1_000);
});

it('keeps refunds to super admins', function (): void {
    $manager = Admin::factory()->manager()->create();
    $payment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'gateway' => 'paystack', 'gateway_ref' => 'x1',
        'status' => Payment::STATUS_SUCCEEDED, 'currency' => 'USD', 'amount' => 5_900,
    ]);

    $this->actingAs($manager, 'admin')
        ->post(route('admin.payments.refund', $payment->id), ['amount' => 100, 'reason' => 'nope'])
        ->assertForbidden();
});

it('creates an operator through the console command', function (): void {
    $exit = Illuminate\Support\Facades\Artisan::call('elitesender:make-admin', [
        '--name' => 'Ops Lead',
        '--email' => 'ops@elitesender.app',
        '--role' => 'admin',
        '--password' => 'Str0ng-Passphrase!42',
    ]);

    expect($exit)->toBe(0)
        ->and(Admin::where('email', 'ops@elitesender.app')->sole()->role)->toBe(Admin::ROLE_ADMIN);
});

it('rejects a weak operator password', function (): void {
    // There is no self-service admin signup and no seeded default account, so
    // this command is the only way in — it must not accept 'password'.
    $exit = Illuminate\Support\Facades\Artisan::call('elitesender:make-admin', [
        '--name' => 'Weak', '--email' => 'weak@elitesender.app', '--password' => 'password',
    ]);

    expect($exit)->toBe(1)
        ->and(Admin::where('email', 'weak@elitesender.app')->exists())->toBeFalse();
});
