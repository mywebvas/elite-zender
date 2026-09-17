<?php

/**
 * Rate limiting — verifies throttle guards fire correctly.
 * Spec: docs/05-API-CONTRACT.md §3 Rate Limiting.
 */
beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('allows up to 60 api requests per minute for starter plan before throttling', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/ping')->assertStatus(200);
    }

    $response = $this->getJson('/api/v1/ping');
    $response->assertStatus(429);
    $response->assertJson([
        'error' => ['code' => 'RATE_LIMITED'],
    ]);
});

it('returns RATE_LIMITED error code in JSON on throttle', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/ping');
    }

    $response = $this->getJson('/api/v1/ping');
    $response->assertStatus(429)
             ->assertJsonPath('error.code', 'RATE_LIMITED');
});
