<?php

/**
 * SecurityHeaders middleware — verifies all required headers are injected.
 * Spec: docs/06-SECURITY-COMPLIANCE.md §1 Application security.
 */

it('injects X-Frame-Options DENY on every web response', function () {
    $response = $this->get('/offline');
    $response->assertHeader('X-Frame-Options', 'DENY');
});

it('injects X-Content-Type-Options nosniff', function () {
    $response = $this->get('/offline');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('injects Referrer-Policy header', function () {
    $response = $this->get('/offline');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('injects Content-Security-Policy header', function () {
    $response = $this->get('/offline');
    $response->assertHeader('Content-Security-Policy');
    // Must include default-src 'self'
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'");
});

it('injects Strict-Transport-Security header', function () {
    $response = $this->get('/offline');
    $response->assertHeader('Strict-Transport-Security');
    expect($response->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=63072000')
        ->toContain('includeSubDomains');
});

it('injects Permissions-Policy header', function () {
    $response = $this->get('/offline');
    $response->assertHeader('Permissions-Policy');
});

it('removes X-Powered-By fingerprinting header', function () {
    $response = $this->get('/offline');
    // Header should not be present
    expect($response->headers->has('X-Powered-By'))->toBeFalse();
});

it('applies security headers to API routes', function () {
    $response = $this->getJson('/api/v1/ping');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});
