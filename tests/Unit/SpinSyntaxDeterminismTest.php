<?php

use App\Services\SpinSyntaxService;

/**
 * docs/08 requires spin selection to be seeded per recipient. The previous
 * implementation used array_rand(), so a retried chunk re-rolled every variant
 * and A/B reporting was meaningless.
 */
it('produces the same variant for the same seed', function (): void {
    $service = new SpinSyntaxService;
    $template = '{Hello|Hi|Hey} {friend|mate|pal}, {check this out|take a look}!';

    $first = $service->compile($template, [], 12345);

    foreach (range(1, 20) as $ignored) {
        expect($service->compile($template, [], 12345))->toBe($first);
    }
});

it('produces different variants for different seeds across a population', function (): void {
    $service = new SpinSyntaxService;
    $template = '{a|b|c|d|e|f|g|h}';

    $results = collect(range(1, 200))->map(fn (int $seed) => $service->compile($template, [], $seed));

    expect($results->unique()->count())->toBeGreaterThan(1);
});

it('does not re-expand merge tag values', function (): void {
    $service = new SpinSyntaxService;

    // A hostile first name must not inject another merge tag. Substitution is
    // a single pass, so the injected placeholder is never resolved — it is
    // simply printed, which is the truthful rendering of that contact's name.
    $result = $service->compile('Hi [Name]', ['Name' => '[Secret]', 'Secret' => 'leaked'], 1);

    expect($result)->not->toContain('leaked')
        ->and(trim($result))->toBe('Hi [Secret]');
});

it('handles option text containing regex metacharacters', function (): void {
    $service = new SpinSyntaxService;

    // preg_replace with a $-sequence used to corrupt the output.
    expect($service->compile('{$100 off|50% off}', [], 7))
        ->toBeIn(['$100 off', '50% off']);
});

it('resolves known tags and leaves the author bracketed copy alone', function (): void {
    $service = new SpinSyntaxService;

    // Deleting every unresolved `[word]` ate real copy: "[URGENT]",
    // "[Webinar]" and "[New]" are ordinary subject-line furniture, and they
    // disappeared silently between the composer and the inbox.
    expect($service->compile('Hello [Name]', ['Name' => 'Ada']))->toBe('Hello Ada')
        ->and($service->compile('[URGENT] Renew today, [name]', ['Name' => 'Ada']))->toBe('[URGENT] Renew today, Ada')
        ->and($service->compile('Hello [Missing]', ['Name' => 'Ada']))->toBe('Hello [Missing]');
});

it('matches merge tags regardless of case', function (): void {
    $service = new SpinSyntaxService;

    expect($service->compile('[NAME] / [name] / [Name]', ['Name' => 'Ada']))->toBe('Ada / Ada / Ada');
});
