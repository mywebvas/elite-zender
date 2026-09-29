<?php

/**
 * SecurityHeaders middleware — verifies all required headers are injected.
 * Spec: docs/06-SECURITY-COMPLIANCE.md §1 Application security.
 */
it('injects X-Frame-Options DENY on every web response', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('X-Frame-Options', 'DENY');
});

it('injects X-Content-Type-Options nosniff', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('injects Referrer-Policy header', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('injects Content-Security-Policy header', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('Content-Security-Policy');
    // Must include default-src 'self'
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'");
});

it('injects Strict-Transport-Security header', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('Strict-Transport-Security');
    expect($response->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=63072000')
        ->toContain('includeSubDomains');
});

it('injects Permissions-Policy header', function (): void {
    $response = $this->get('/offline');
    $response->assertHeader('Permissions-Policy');
});

it('removes X-Powered-By fingerprinting header', function (): void {
    $response = $this->get('/offline');
    // Header should not be present
    expect($response->headers->has('X-Powered-By'))->toBeFalse();
});

it('applies security headers to API routes', function (): void {
    $response = $this->getJson('/api/v1/ping');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

/**
 * Clickjacking protection must stay on unless an operator deliberately,
 * explicitly opts a named origin in. And `X-Powered-By` has to be removed
 * from PHP's SAPI header, not only from the Symfony response bag — the bag
 * never held it, so the version banner shipped on every response.
 */
it('denies framing by default', function (): void {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('X-Powered-By');

    expect($this->get('/login')->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
});

it('honours an explicit frame-ancestors allow list', function (): void {
    config(['app.frame_ancestors' => ['https://portal.example.com']]);

    $response = $this->get('/login');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('frame-ancestors https://portal.example.com')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});
