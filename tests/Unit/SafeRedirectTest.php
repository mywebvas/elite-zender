<?php

use App\Support\SafeRedirect;

it('allows ordinary public https links', function (string $url): void {
    expect(SafeRedirect::isAllowed($url))->toBeTrue();
})->with([
    'https://example.com/promo',
    'http://example.com/promo?utm_source=email',
    'https://sub.domain.example.com/a/b/c#anchor',
]);

it('blocks non-http schemes, loopback, private ranges and credential URLs', function (string $url): void {
    expect(SafeRedirect::isAllowed($url))->toBeFalse();
})->with([
    'javascript:alert(1)',
    'file:///etc/passwd',
    'http://localhost/admin',
    'http://127.0.0.1:8080/',
    'http://10.0.0.5/internal',
    'http://192.168.1.1/router',
    'http://169.254.169.254/latest/meta-data/',   // cloud metadata
    'http://user:pass@example.com/',              // phishing disguise
    'http://printer.local/print',
    'http://vault.internal/secret',
    '',
]);
