<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Rate limiting — verifies throttle guards fire correctly.
 * Spec: docs/05-API-CONTRACT.md §3 Rate Limiting.
 *
 * The tier is read from the workspace's *subscription*. It used to be read
 * from a `settings.plan` key that nothing has ever written, so every
 * workspace silently landed on the same limit while the 429 body advised the
 * customer to upgrade for headroom that upgrading could not produce.
 */
function pingUntilThrottled(int $max = 2000): int
{
    for ($i = 1; $i <= $max; $i++) {
        if (test()->getJson('/api/v1/ping')->getStatusCode() === 429) {
            return $i - 1;
        }
    }

    return $max;
}

it('gives a workspace with no subscription the free-tier allowance', function (): void {
    $this->actingAs(User::factory()->create());

    expect(pingUntilThrottled(120))->toBe(30);
});

it('raises the allowance to match the plan the workspace pays for', function (string $code, int $expected): void {
    seedPlans();

    $user = User::factory()->create();

    Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $user->tenant_id,
        'plan_id' => Plan::query()->where('code', $code)->sole()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => 0,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($user);

    expect(pingUntilThrottled($expected + 5))->toBe($expected);
})->with([
    'free' => ['free', 30],
    'starter' => ['starter', 60],
    'growth' => ['growth', 180],
]);

it('returns RATE_LIMITED error code in JSON on throttle', function (): void {
    $this->actingAs(User::factory()->create());

    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/v1/ping');
    }

    $this->getJson('/api/v1/ping')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMITED');
});
