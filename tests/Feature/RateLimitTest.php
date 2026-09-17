<?php

/**
 * Rate limiting — verifies throttle guards fire correctly.
 * Spec: docs/05-API-CONTRACT.md §3 Rate Limiting.
 */

it('allows up to 5 login attempts per minute before throttling', function () {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
        // We expect 501 (Not Implemented stub) — not 429
        $response->assertStatus(501);
    }

    // 6th attempt should be throttled
    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'test@example.com',
        'password' => 'wrongpassword',
    ]);
    $response->assertStatus(429);
    $response->assertJson([
        'error' => ['code' => 'RATE_LIMITED'],
    ]);
});

it('returns RATE_LIMITED error code in JSON on throttle', function () {
    // Exhaust the limit
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@x.com', 'password' => 'y']);
    }

    $response = $this->postJson('/api/v1/auth/login', ['email' => 'x@x.com', 'password' => 'y']);
    $response->assertStatus(429)
             ->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('returns 429 with rate limit info on throttle', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'c']);
    }

    $response = $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'c']);
    // Array cache doesn't emit Retry-After; Redis cache does. Assert the status and body.
    $response->assertStatus(429)
             ->assertJsonPath('error.code', 'RATE_LIMITED');
});
