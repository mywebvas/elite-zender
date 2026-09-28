<?php

/**
 * The landing page is the only public surface, and it shipped with a <title>
 * and a description and nothing else — no canonical, no Open Graph, no Twitter
 * card, no structured data. A shared link rendered as a bare URL.
 */
it('exposes complete metadata on the marketing page', function (): void {
    $response = $this->get('/')->assertOk();

    $response->assertSee('<link rel="canonical"', escape: false)
        ->assertSee('property="og:title"', escape: false)
        ->assertSee('property="og:image"', escape: false)
        ->assertSee('name="twitter:card"', escape: false)
        ->assertSee('application/ld+json', escape: false)
        ->assertSee('"@type":"SoftwareApplication"', escape: false)
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('apple-touch-icon', escape: false);
});

it('gives the inline theme script a CSP nonce', function (): void {
    $response = $this->get('/')->assertOk();

    // Without a nonce the browser blocks it and every dark-mode visitor gets
    // a white flash on first paint.
    preg_match('/<script nonce="([^"]+)">\(function\(\)\{var t=localStorage/', $response->getContent(), $m);

    expect($m[1] ?? null)->not->toBeNull()
        ->and(strlen($m[1]))->toBe(32)
        ->and($response->headers->get('Content-Security-Policy'))->toContain("'nonce-{$m[1]}'");
});

it('has one main landmark and a skip link', function (): void {
    $content = $this->get('/')->assertOk()->getContent();

    expect(substr_count($content, '<main id="main">'))->toBe(1)
        ->and($content)->toContain('Skip to main content');
});

it('serves a sitemap listing only public pages', function (): void {
    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $response->assertSee('<urlset', escape: false)
        ->assertSee(url('/'), escape: false)
        ->assertDontSee('/dashboard')
        ->assertDontSee('/contacts');
});

it('keeps crawlers out of the authenticated app', function (): void {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /api/')
        ->toContain('Disallow: /unsubscribe/')
        ->toContain('Sitemap:');
});

it('marks authenticated pages as no-store', function (): void {
    actingAsTenantUser();

    // Otherwise a contact list stays readable via the back button after logout.
    $cacheControl = $this->get(route('dashboard'))->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')
        ->and($cacheControl)->toContain('private');
});
