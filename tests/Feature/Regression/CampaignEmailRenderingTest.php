<?php

use App\Mail\CampaignEmail;

/**
 * Regression: CampaignEmail::content() passed `textString:` to
 * Illuminate\Mail\Mailables\Content, which has no such parameter. Every real
 * send raised "Unknown named parameter $textString" — invisible in the suite
 * because Mail::fake() never renders the mailable.
 *
 * These tests render for real.
 */
it('renders both html and plain text bodies without throwing', function (): void {
    $mailable = new CampaignEmail(
        campaignSubject: 'Spring sale',
        htmlBody: '<p>Hello <strong>world</strong></p>',
        textBody: "Hello world\nVisit us",
        unsubUrl: 'https://example.com/unsubscribe/abc',
    );

    $rendered = $mailable->render();

    expect($rendered)->toContain('Hello <strong>world</strong>');

    $built = $mailable->to('recipient@example.com')->from('sender@example.com', 'Sender');
    $message = $built->withSymfonyMessage(fn () => null)->buildViewData();

    expect($message)->toBeArray();
});

it('renders when there is no plain text alternative', function (): void {
    $mailable = new CampaignEmail('Subject only', '<p>Body</p>');

    expect($mailable->render())->toContain('Body');
});

it('attaches RFC 8058 one-click unsubscribe headers', function (): void {
    $mailable = new CampaignEmail('Subject', '<p>Body</p>', '', 'https://example.com/u/1');

    $envelope = $mailable->envelope();

    $names = array_map(fn ($header) => $header->getName(), $envelope->using);

    expect($names)->toContain('List-Unsubscribe')
        ->and($names)->toContain('List-Unsubscribe-Post');
});

it('omits unsubscribe headers when no url is available', function (): void {
    expect((new CampaignEmail('Subject', '<p>Body</p>'))->envelope()->using)->toBe([]);
});
