<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $campaignSubject;
    public string $htmlBody;
    public string $textBody;
    public ?string $unsubUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $htmlBody, string $textBody = '', ?string $unsubUrl = null)
    {
        $this->campaignSubject = $subject;
        $this->htmlBody = $htmlBody;
        $this->textBody = $textBody;
        $this->unsubUrl = $unsubUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $headers = [];
        if ($this->unsubUrl) {
            $headers = [
                new \Symfony\Component\Mime\Header\UnstructuredHeader('List-Unsubscribe', '<' . $this->unsubUrl . '>'),
                new \Symfony\Component\Mime\Header\UnstructuredHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'),
            ];
        }

        return new Envelope(
            subject: $this->campaignSubject,
            using: $headers,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            textString: $this->textBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
