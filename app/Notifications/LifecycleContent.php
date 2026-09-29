<?php

namespace App\Notifications;

/**
 * The content of one lifecycle email, as data.
 *
 * Every message in the lifecycle is the same shape — an eyebrow, a headline,
 * a few sentences, an optional table of facts, one call to action — so it is
 * described rather than hand-built. That is what keeps twelve emails looking
 * like one product, and what lets the plain-text alternative be generated
 * instead of maintained separately (a missing text/plain part is a textbook
 * spam signal, and hand-written ones rot immediately).
 */
final class LifecycleContent
{
    /**
     * @param  list<string>  $lines  body paragraphs; inline HTML is allowed
     * @param  array<string, string>  $facts  label => value summary table
     * @param  list<string>  $outro  small print beneath the call to action
     * @param  'brand'|'success'|'warning'|'danger'  $tone
     */
    public function __construct(
        public string $subject,
        public string $heading,
        public string $greetingName,
        public array $lines,
        public ?string $eyebrow = null,
        public ?string $preheader = null,
        public array $facts = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public array $outro = [],
        public string $tone = 'brand',
    ) {
        // The preheader is prime inbox real estate; default it to the first
        // sentence rather than leaving the client to scrape raw markup.
        $this->preheader ??= trim(strip_tags($lines[0] ?? $heading));
    }

    public function accent(): string
    {
        return match ($this->tone) {
            'success' => '#059669',
            'warning' => '#d97706',
            'danger' => '#e11d48',
            default => '#4f46e5',
        };
    }

    /**
     * Plain-text alternative, derived from the same source as the HTML so the
     * two can never say different things.
     */
    public function toPlainText(): string
    {
        $parts = ["Hi {$this->greetingName},", ''];

        foreach ($this->lines as $line) {
            $parts[] = $this->flatten($line);
            $parts[] = '';
        }

        foreach ($this->facts as $label => $value) {
            $parts[] = "{$label}: {$value}";
        }

        if ($this->facts !== []) {
            $parts[] = '';
        }

        if ($this->actionUrl !== null) {
            $parts[] = trim((string) $this->actionLabel).': '.$this->actionUrl;
            $parts[] = '';
        }

        foreach ($this->outro as $line) {
            $parts[] = $this->flatten($line);
        }

        $parts[] = '';
        $parts[] = '— The '.config('platform.name').' team';
        $parts[] = 'Questions? '.config('platform.support_email');

        return implode("\n", $parts);
    }

    private function flatten(string $html): string
    {
        // Keep the destination of a link rather than dropping it: "click here"
        // with no URL is useless in a text part.
        $withUrls = preg_replace('/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);

        return trim(html_entity_decode(strip_tags((string) $withUrls), ENT_QUOTES | ENT_HTML5));
    }
}
