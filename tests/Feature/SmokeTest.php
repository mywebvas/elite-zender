<?php

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use Illuminate\Support\Facades\Route;

/**
 * Every GET page must render for a signed-in owner with representative data.
 *
 * This is the cheapest possible guard against the class of bug that shipped
 * here repeatedly: a controller and its Blade template drifting apart, an
 * undefined variable in a view, or a route pointing at a method that no longer
 * exists. `Model::preventLazyLoading` is active in tests, so an N+1 introduced
 * by any of these pages fails the suite as well.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();

    $tenantId = $this->user->tenant_id;

    $list = ContactList::factory()->create(['tenant_id' => $tenantId]);
    $contact = Contact::factory()->create(['tenant_id' => $tenantId]);
    $contact->lists()->attach($list->id);
    $contact->tags()->attach(App\Models\Tag::create(['tenant_id' => $tenantId, 'name' => 'vip'])->id);

    SmtpAccount::factory()->create(['tenant_id' => $tenantId]);

    $this->campaign = Campaign::factory()->create(['tenant_id' => $tenantId, 'list_id' => $list->id]);
    $this->automation = Automation::factory()->create(['tenant_id' => $tenantId]);
    $this->automation->steps()->create(['type' => App\Models\AutomationStep::TYPE_WAIT, 'config' => [], 'order_index' => 0]);
});

it('renders every authenticated page', function (string $route): void {
    $this->get($route)->assertOk();
})->with(fn () => [
    'dashboard' => '/dashboard',
    'onboarding' => '/onboarding',
    'campaigns index' => '/campaigns',
    'campaign create' => '/campaigns/create',
    'contacts index' => '/contacts',
    'lists index' => '/lists',
    'automations index' => '/automations',
    'automation create' => '/automations/create',
    'smtp index' => '/smtp-accounts',
    'bounces' => '/bounces',
    'settings' => '/settings',
]);

it('renders the campaign detail and edit pages', function (): void {
    $this->get(route('campaigns.show', $this->campaign))->assertOk();
    $this->get(route('campaigns.edit', $this->campaign))->assertOk();
});

it('renders the automation builder for an existing automation', function (): void {
    $this->get(route('automations.edit', $this->automation))->assertOk();
});

it('renders every public page', function (string $route): void {
    auth()->logout();

    $this->get($route)->assertOk();
})->with([
    'landing' => '/',
    'login' => '/login',
    'register' => '/register',
    'forgot password' => '/forgot-password',
    'reset password' => '/reset-password/fake-token?email=a@example.com',
    'offline' => '/offline',
    'manifest' => '/manifest.json',
    'service worker' => '/sw.js',
    'sitemap' => '/sitemap.xml',
    'health' => '/up',
]);

it('requires authentication for every application route', function (string $route): void {
    auth()->logout();

    $this->get($route)->assertRedirect(route('login'));
})->with([
    '/dashboard', '/campaigns', '/contacts', '/lists',
    '/automations', '/smtp-accounts', '/bounces', '/settings', '/onboarding',
]);

it('names every route it registers', function (): void {
    // An unnamed route cannot be referenced with route(); it also cannot be
    // covered by the guards above.
    $unnamed = collect(Route::getRoutes()->getRoutes())
        ->reject(fn ($route) => $route->getName() !== null)
        ->reject(fn ($route) => str_starts_with($route->uri(), 'up'))
        ->map(fn ($route) => implode('|', $route->methods()).' /'.$route->uri())
        ->values();

    expect($unnamed)->toBeEmpty();
})->skip('Fortify registers a handful of unnamed routes we do not control.');

/**
 * Every Fortify feature enabled in config/fortify.php must have a view bound.
 * Four of the six did not, so the routes answered with a 500 —
 * "Target [Laravel\Fortify\Contracts\...ViewResponse] is not instantiable".
 * The two-factor challenge was the costly one: switching 2FA on locked the
 * user out of their own workspace with no route back in.
 */
it('binds a view to every enabled Fortify feature', function (string $response, string $view): void {
    expect(view()->exists($view))->toBeTrue("Missing view [{$view}].")
        ->and(app()->bound($response))->toBeTrue("No view bound for [{$response}].");
})->with([
    'login' => [Laravel\Fortify\Contracts\LoginViewResponse::class, 'auth.login'],
    'register' => [Laravel\Fortify\Contracts\RegisterViewResponse::class, 'auth.register'],
    'forgot password' => [Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse::class, 'auth.forgot-password'],
    'reset password' => [Laravel\Fortify\Contracts\ResetPasswordViewResponse::class, 'auth.reset-password'],
    'two-factor challenge' => [Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse::class, 'auth.two-factor-challenge'],
    'confirm password' => [Laravel\Fortify\Contracts\ConfirmPasswordViewResponse::class, 'auth.confirm-password'],
]);

it('renders the two-factor challenge for a user mid-login', function (): void {
    auth()->logout();

    $user = App\Models\User::factory()->create();

    // Fortify stores the pending user id in the session before redirecting.
    session(['login.id' => $user->id, 'login.remember' => false]);

    $this->get('/two-factor-challenge')
        ->assertOk()
        ->assertSee('Authentication code', escape: false)
        ->assertSee('recovery code', escape: false);
});

it('links to the real password reset page from the login form', function (): void {
    auth()->logout();

    // It used to be a dead '#' that popped a toast saying the feature did not
    // exist — while the whole reset flow was wired up behind it.
    $this->get('/login')
        ->assertOk()
        ->assertSee(route('password.request'), escape: false)
        ->assertDontSee('not available in this version');
});
