<?php

/**
 * PWA manifest + service worker — verifies installability requirements.
 * Spec: docs/07-PWA-SPEC.md §5.1
 */
test('manifest.json returns 200 with correct content type', function (): void {
    $response = $this->get('/manifest.json');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/json');
});

test('manifest.json contains required name and short_name', function (): void {
    $response = $this->get('/manifest.json');
    $data = $response->json();

    expect($data['name'])->toBe('EliteSender');
    expect($data['short_name'])->toBe('EliteSender');
});

test('manifest.json has standalone display mode', function (): void {
    $response = $this->get('/manifest.json');
    expect($response->json('display'))->toBe('standalone');
});

test('manifest.json start_url is dashboard', function (): void {
    $response = $this->get('/manifest.json');
    expect($response->json('start_url'))->toBe('/dashboard');
});

test('manifest.json contains at least one icon', function (): void {
    $response = $this->get('/manifest.json');
    $icons = $response->json('icons');

    expect($icons)->toBeArray()->not->toBeEmpty();
    // Must have 192 and 512 sizes
    $sizes = collect($icons)->pluck('sizes')->all();
    expect($sizes)->toContain('192x192')
        ->toContain('512x512');
});

test('manifest.json has correct brand theme_color', function (): void {
    $response = $this->get('/manifest.json');
    expect($response->json('theme_color'))->toBe('#4f46e5');
});

test('service worker file is publicly accessible', function (): void {
    $response = $this->get('/sw.js');
    $response->assertStatus(200);
    // Must be JavaScript
    expect($response->headers->get('Content-Type'))->toContain('javascript');
});

test('offline fallback page is accessible without auth', function (): void {
    $response = $this->get('/offline');
    $response->assertStatus(200);
    $response->assertSee('offline', false);
});
