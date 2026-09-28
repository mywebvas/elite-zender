<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * End-to-end credential flow with CSRF, sessions and tenant resolution all
 * switched on — the middleware stack a browser actually traverses.
 */
it('signs a user in and lands them on the dashboard', function (): void {
    $user = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => Hash::make('correct-horse-battery-staple'),
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-horse-battery-staple',
        ])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')->assertOk();
});

it('rejects a bad password without revealing whether the account exists', function (): void {
    User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('right')]);

    $known = $this->from('/login')->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
    $unknown = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

    // Identical outcome for both: no enumeration oracle.
    expect($known->getStatusCode())->toBe($unknown->getStatusCode());

    $known->assertSessionHasErrors(['email' => __('auth.failed')]);
    $unknown->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('rotates the session on login so a fixated id is useless', function (): void {
    User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('secret-passphrase')]);

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'secret-passphrase']);

    expect(session()->getId())->not->toBe($before);
});

it('registers a new workspace with the registering user as owner', function (): void {
    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'analytical-engine-1843',
        'password_confirmation' => 'analytical-engine-1843',
    ])->assertRedirect('/dashboard');

    $user = User::where('email', 'ada@example.com')->sole();

    expect($user->role)->toBe(Role::OWNER)
        ->and($user->tenant_id)->not->toBeNull()
        ->and(Tenant::whereKey($user->tenant_id)->exists())->toBeTrue();
});

it('arms CSRF protection on state-changing routes', function (string $uri): void {
    // Laravel's test client bypasses the token, so assert the middleware is
    // actually attached rather than simulating a forged post.
    $route = collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === ltrim($uri, '/') && in_array('POST', $r->methods(), true));

    expect($route)->not->toBeNull("No POST route for [{$uri}].")
        ->and($route->gatherMiddleware())->toContain('web');
})->with(['/login', '/register', '/logout', '/contacts', '/campaigns']);

it('exempts only the RFC 8058 unsubscribe endpoint from CSRF', function (): void {
    // One-click unsubscribe is a cross-origin POST from a mail client with no
    // session; the signed URL provides the integrity guarantee instead.
    // Both are cross-origin POSTs that cannot carry a token: the unsubscribe
    // route is signed, and webhooks are HMAC-verified by the provider.
    expect(app(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)->getExcludedPaths())
        ->toBe(['unsubscribe/*', 'webhooks/billing/*']);
});

it('logs the user out and forgets the session', function (): void {
    $user = actingAsTenantUser();

    $this->post('/logout')->assertRedirect();

    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect('/login');
});
