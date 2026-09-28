<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests get a fresh, transaction-wrapped database and the application
| test case. Unit tests get the application container without touching the
| database so they stay fast.
|
*/

// RefreshDatabase is applied by Tests\TestCase itself so that plain PHPUnit
// classes cannot slip through without a migrated database.
pest()->extend(Tests\TestCase::class)->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/**
 * Assert a response carries a security header with the expected value.
 *
 * The previous version returned `toHaveMethod('assertHeader')` — it asserted
 * that the response object *has a method*, which is true of every response and
 * therefore never failed. Tests that cannot fail are worse than no tests.
 */
expect()->extend('toHaveSecurityHeader', function (string $header, ?string $contains = null) {
    /** @var Illuminate\Testing\TestResponse $response */
    $response = $this->value;

    $value = $response->headers->get($header);

    expect($value)->not->toBeNull("Expected response to carry the [{$header}] header.");

    if ($contains !== null) {
        expect($value)->toContain($contains);
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Seed the plan catalogue. Tests that exercise billing or plan limits need it;
 * the rest deliberately run without it, which also proves the application
 * degrades sensibly on an unseeded install.
 */
function seedPlans(): void
{
    (new Database\Seeders\PlanSeeder)->run();
}

/**
 * Create a signed-in user bound to a fresh tenant, and bind the tenant context
 * so model global scopes behave exactly as they do behind the middleware.
 */
function actingAsTenantUser(array $attributes = []): App\Models\User
{
    $user = App\Models\User::factory()->create($attributes);

    App\Tenancy\TenantContext::set($user->tenant);

    test()->actingAs($user);

    return $user;
}
