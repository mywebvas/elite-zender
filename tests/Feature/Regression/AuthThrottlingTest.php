<?php

use Illuminate\Support\Facades\Route;

/**
 * Regression: config/fortify.php only lets you wire limiters to the login,
 * two-factor and passkey routes. Password reset and registration shipped
 * completely unthrottled — free account enumeration and a ready-made email
 * bomb — so FortifyServiceProvider attaches limiters to them explicitly.
 */
beforeEach(function (): void {
    $this->withoutMiddleware(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('rate limits the routes Fortify leaves unprotected', function (string $routeName, string $limiter): void {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull("Route [{$routeName}] is missing.")
        ->and($route->gatherMiddleware())->toContain("throttle:{$limiter}");
})->with([
    'forgot password' => ['password.email', 'reset-password'],
    'reset password' => ['password.update', 'reset-password'],
    // 'register' is the GET view route; the POST handler is 'register.store'.
    // Throttling the wrong one reads as done and protects nothing.
    'registration' => ['register.store', 'register'],
]);

it('registers every limiter the routes reference', function (string $limiter): void {
    expect(app(Illuminate\Cache\RateLimiter::class)->limiter($limiter))->not->toBeNull();
})->with(['login', 'two-factor', 'passkeys', 'reset-password', 'register', 'api', 'tracking', 'csv-import', 'campaign-dispatch']);

it('throttles registration by IP', function (): void {
    foreach (range(1, 10) as $i) {
        $this->post('/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);
    }

    // A browser form post gets a redirect carrying the error, not a raw 429
    // body; an API client asking for JSON gets the 429 (asserted below).
    $this->post('/register', [
        'name' => 'One too many',
        'email' => 'overflow@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect()->assertSessionHasErrors();

    $this->postJson('/register', [
        'name' => 'One too many',
        'email' => 'overflow2@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('returns an HTML redirect rather than raw JSON when a browser hits the login throttle', function (): void {
    $response = null;

    foreach (range(1, 6) as $ignored) {
        $response = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    // The old limiter always answered with a JSON body, even to a form post,
    // so a user who mistyped their password five times was shown a raw blob.
    expect($response->headers->get('Content-Type'))->not->toContain('application/json');
    $response->assertRedirect();
});
