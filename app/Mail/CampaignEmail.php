<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * A single rendered campaign message.
 *
 * Bodies arrive pre-rendered (spin syntax expanded, tracking injected), so we
 * hand Laravel a raw HTML string and render the plain-text alternative through
 * a passthrough view — `Content` only accepts *view names* for the text part,
 * there is no `textString` parameter.
 */
class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $campaignSubject,
        public string $htmlBody,
        public string $textBody = '',
        public ?string $unsubUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $headers = [];

        if ($this->unsubUrl !== null && $this->unsubUrl !== '') {
            // RFC 8058 one-click unsubscribe. Gmail/Yahoo require both headers
            // for bulk senders; List-Unsubscribe-Post makes the POST variant
            // valid without any user interaction.
            $headers = [
                new UnstructuredHeader('List-Unsubscribe', '<'.$this->unsubUrl.'>'),
                new UnstructuredHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'),
            ];
        }

        return new Envelope(
            subject: $this->campaignSubject,
            using: $headers,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: $this->textBody === '' ? null : 'emails.campaign-plain',
            with: ['plain' => $this->textBody],
            htmlString: $this->htmlBody,
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
