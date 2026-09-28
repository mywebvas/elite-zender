<?php

use App\Services\SpinSyntaxService;

test('it replaces shortcodes', function (): void {
    $service = new SpinSyntaxService;
    $result = $service->compile('Hello [Name]', ['Name' => 'Alice']);
    expect($result)->toBe('Hello Alice');
});

test('it removes empty shortcodes', function (): void {
    $service = new SpinSyntaxService;
    $result = $service->compile('Hello [Name] [LastName]', ['Name' => 'Alice']);
    // Since LastName is not provided, it should be removed.
    // Notice the space is still there before [LastName] so "Hello Alice " is expected.
    expect(trim($result))->toBe('Hello Alice');
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
