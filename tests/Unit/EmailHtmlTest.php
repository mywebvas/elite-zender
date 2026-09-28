<?php

use App\Services\EmailHtmlRenderer;
use App\Services\EmailHtmlSanitizer;

beforeEach(function (): void {
    $this->sanitizer = new EmailHtmlSanitizer;
    $this->renderer = new EmailHtmlRenderer($this->sanitizer);
});

/*
|--------------------------------------------------------------------------
| Sanitiser — campaign HTML is rendered back into the workspace UI, so an
| injected script is stored XSS against the operator's own colleagues.
|--------------------------------------------------------------------------
*/

it('removes script, style and frame elements entirely', function (string $payload): void {
    expect($this->sanitizer->sanitize($payload))
        ->not->toContain('alert')
        ->not->toContain('<script')
        ->not->toContain('<iframe')
        ->not->toContain('<style');
})->with([
    '<p>Hi</p><script>alert(1)</script>',
    '<style>body{background:url(javascript:alert(1))}</style><p>Hi</p>',
    '<iframe src="https://evil.test"></iframe>',
    '<object data="evil.swf"></object>',
    '<svg onload="alert(1)"></svg>',
]);

it('strips every event handler attribute', function (): void {
    $clean = $this->sanitizer->sanitize('<p onclick="alert(1)" onmouseover="steal()">Hello</p>');

    expect($clean)->toContain('Hello')
        ->not->toContain('onclick')
        ->not->toContain('onmouseover');
});

it('drops javascript and data URLs but keeps legitimate ones', function (): void {
    $clean = $this->sanitizer->sanitize(
        '<a href="javascript:alert(1)">bad</a>'
        .'<a href="data:text/html;base64,PHNjcmlwdD4=">worse</a>'
        .'<a href="https://example.com">good</a>'
        .'<a href="mailto:hi@example.com">mail</a>',
    );

    expect($clean)->not->toContain('javascript:')
        ->not->toContain('data:text/html')
        ->toContain('https://example.com')
        ->toContain('mailto:hi@example.com');
});

it('keeps merge tags in href attributes intact', function (): void {
    // Personalised links are a core feature; the URL filter must not eat them.
    expect($this->sanitizer->sanitize('<a href="[ProfileUrl]">Profile</a>'))
        ->toContain('[ProfileUrl]');
});

it('unwraps unknown elements instead of deleting their content', function (): void {
    // Losing an operator's copy because they pasted a <section> is worse than
    // losing the wrapper.
    expect($this->sanitizer->sanitize('<section><p>Keep me</p></section>'))
        ->toContain('Keep me')
        ->not->toContain('<section');
});

it('filters dangerous css but keeps presentational declarations', function (): void {
    $clean = $this->sanitizer->sanitize(
        '<p style="color: #ff0000; background-image: url(javascript:alert(1)); position: fixed">Text</p>',
    );

    expect($clean)->toContain('color: #ff0000')
        ->not->toContain('javascript')
        ->not->toContain('position');
});

it('adds rel=noopener to links that open a new tab', function (): void {
    expect($this->sanitizer->sanitize('<a href="https://example.com" target="_blank">x</a>'))
        ->toContain('noopener');
});

it('preserves non-ascii content', function (): void {
    expect($this->sanitizer->sanitize('<p>Café — Ẹ káàbọ̀ 你好</p>'))
        ->toContain('Café')
        ->toContain('Ẹ káàbọ̀')
        ->toContain('你好');
});

/*
|--------------------------------------------------------------------------
| Renderer — Quill styles with classes and ships no stylesheet, so without
| inlining every campaign arrives unstyled and left-aligned.
|--------------------------------------------------------------------------
*/

it('converts editor classes into inline styles', function (): void {
    $html = $this->renderer->renderFragment('<p class="ql-align-center">Centred</p>');

    expect($html)->toContain('text-align: center')
        ->not->toContain('ql-align-center');
});

it('inlines baseline typography on block elements', function (): void {
    $html = $this->renderer->renderFragment('<h1>Title</h1><p>Body</p><a href="https://x.test">Link</a>');

    expect($html)->toContain('font-size: 28px')   // h1
        ->toContain('line-height: 1.6')            // p
        ->toContain('text-decoration: underline'); // a
});

it('lets author styles win over the baseline', function (): void {
    $html = $this->renderer->renderFragment('<p style="color: rgb(255, 0, 0)">Red</p>');

    // The author declaration is emitted last, so the cascade keeps it.
    expect($html)->toContain('color: rgb(255, 0, 0)')
        ->and(strpos($html, 'color: rgb(255, 0, 0)'))->toBeGreaterThan(strpos($html, 'line-height'));
});

it('wraps content in the table scaffold Outlook needs', function (): void {
    $html = $this->renderer->render('<p>Hello</p>');

    expect($html)->toContain('<!DOCTYPE html')
        ->toContain('role="presentation"')
        ->toContain('width="600"')
        ->toContain('[if mso]')
        ->toContain('Hello');
});

it('hides the preheader from the body but keeps it in the source', function (): void {
    $html = $this->renderer->render('<p>Body copy</p>', 'This shows next to the subject');

    expect($html)->toContain('This shows next to the subject')
        ->toContain('mso-hide: all')
        ->toContain('display: none');
});

it('escapes the preheader', function (): void {
    expect($this->renderer->render('<p>x</p>', '<script>alert(1)</script>'))
        ->not->toContain('<script>');
});

it('omits the preheader block when none is given', function (): void {
    expect($this->renderer->render('<p>x</p>'))->not->toContain('mso-hide');
});

it('derives readable plain text, surfacing link targets', function (): void {
    $text = $this->renderer->toPlainText(
        '<h1>Sale</h1><p>Up to <strong>50%</strong> off.</p>'
        .'<ul><li>Shoes</li><li>Bags</li></ul>'
        .'<p><a href="https://shop.test/sale">Shop now</a></p>',
    );

    expect($text)->toContain('Sale')
        ->toContain('Up to 50% off.')
        ->toContain('• Shoes')
        ->toContain('Shop now (https://shop.test/sale)')
        ->not->toContain('<h1>');
});

it('collapses runaway whitespace in the plain text part', function (): void {
    expect($this->renderer->toPlainText('<p>a</p><p></p><p></p><p></p><p>b</p>'))
        ->toBe("a\n\nb");
});

it('returns empty output for empty input rather than an empty shell', function (): void {
    expect($this->sanitizer->sanitize(''))->toBe('')
        ->and($this->renderer->renderFragment('   '))->toBe('')
        ->and($this->renderer->toPlainText(''))->toBe('');
});
