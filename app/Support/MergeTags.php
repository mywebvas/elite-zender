<?php

namespace App\Support;

use App\Models\Contact;

/**
 * The single source of truth for personalisation tokens.
 *
 * The composer's inserter, the preview, the test send and the live send
 * pipeline all read this list, so the UI can never offer a tag the renderer
 * does not resolve — the same drift that broke the automation builder.
 */
final class MergeTags
{
    /**
     * @return array<int, array{tag: string, label: string, sample: string}>
     */
    public static function available(): array
    {
        return [
            ['tag' => '[Name]', 'label' => 'First name', 'sample' => 'Ada'],
            ['tag' => '[first_name]', 'label' => 'First name (raw)', 'sample' => 'Ada'],
            ['tag' => '[last_name]', 'label' => 'Last name', 'sample' => 'Lovelace'],
            ['tag' => '[Email]', 'label' => 'Email address', 'sample' => 'ada@example.com'],
        ];
    }

    /**
     * Data used to render previews and test sends.
     *
     * Falls back to representative sample values when the workspace has no
     * contacts yet, so a brand-new account still gets a meaningful preview
     * instead of a body full of blanks.
     *
     * @return array<string, string>
     */
    public static function sampleData(?Contact $contact = null): array
    {
        $defaults = [];

        foreach (self::available() as $tag) {
            $defaults[trim($tag['tag'], '[]')] = $tag['sample'];
        }

        if ($contact === null) {
            return $defaults;
        }

        return array_merge($defaults, array_filter([
            'Name' => $contact->first_name,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'Email' => $contact->email,
        ], static fn ($value) => filled($value)));
    }
}
