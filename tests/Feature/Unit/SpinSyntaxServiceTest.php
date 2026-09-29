<?php

use App\Services\SpinSyntaxService;

test('it replaces shortcodes', function (): void {
    $service = new SpinSyntaxService;
    $result = $service->compile('Hello [Name]', ['Name' => 'Alice']);
    expect($result)->toBe('Hello Alice');
});

test('it renders a known shortcode with no value as nothing', function (): void {
    $service = new SpinSyntaxService;

    // The send pipeline always supplies the full tag vocabulary (see
    // MergeTags::forContact), with empty strings where the contact has no
    // value — so a missing surname collapses rather than printing a tag.
    $result = $service->compile('Hello [Name] [last_name]', ['Name' => 'Alice', 'last_name' => '']);

    expect(trim($result))->toBe('Hello Alice');
});

test('it leaves bracketed copy that is not a merge tag intact', function (): void {
    $service = new SpinSyntaxService;

    expect($service->compile('[SALE] Hello [Name]', ['Name' => 'Alice']))->toBe('[SALE] Hello Alice');
});

test('it compiles spintax', function (): void {
    $service = new SpinSyntaxService;
    $result = $service->compile('{Hello|Hi|Hey}');
    expect(['Hello', 'Hi', 'Hey'])->toContain($result);
});

test('it compiles nested spintax', function (): void {
    $service = new SpinSyntaxService;
    // Inner resolves first. E.g. {friend|mate} -> friend. Outer -> {Hello friend|Hi}
    $result = $service->compile('{Hello {friend|mate}|Hi}');
    expect(['Hello friend', 'Hello mate', 'Hi'])->toContain($result);
});

test('it compiles combined spintax and shortcodes', function (): void {
    $service = new SpinSyntaxService;
    $result = $service->compile('{Hello|Hi} [Name]', ['Name' => 'Alice']);
    expect(['Hello Alice', 'Hi Alice'])->toContain($result);
});
