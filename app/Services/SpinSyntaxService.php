<?php

namespace App\Services;

/**
 * Merge-tag substitution + spin syntax expansion.
 *
 * Spin selection is **seeded per recipient** (docs/08-MIGRATION-PLAN.md): the
 * same contact always receives the same variant, so a resend after a worker
 * crash is idempotent and variant-level analytics stay meaningful. The old
 * implementation used `array_rand()`, which re-rolled on every render.
 */
class SpinSyntaxService
{
    /** Guard against pathological nesting such as `{{{{a|b}}}}`. */
    private const MAX_PASSES = 100;

    /**
     * Parse a template like "{Hello|Hi} [Name]" and substitute merge tags.
     *
     * @param  array<string, mixed>  $data  merge tags, e.g. ['Name' => 'John']
     * @param  int|null  $seed  deterministic variant seed (recipient-scoped)
     */
    public function compile(string $text, array $data = [], ?int $seed = null): string
    {
        return $this->processSpintax($this->replaceShortcodes($text, $data), $seed);
    }

    /**
     * Substitute `[Tag]` placeholders from the supplied data.
     *
     * Two properties this has to hold, and the naive implementations break
     * one or the other:
     *
     *  - **No re-expansion.** A contact whose first name is literally
     *    `[Secret]` must not cause `[Secret]` to be resolved. A single
     *    `preg_replace_callback` pass guarantees it: replacement text is
     *    never rescanned, so injected placeholders cannot chain.
     *
     *  - **No silent content loss.** The previous version deleted *every*
     *    `[word]` it could not resolve, which quietly ate `[URGENT]`,
     *    `[Webinar]` and `[New]` out of subject lines — some of the most
     *    common copy in email marketing — with no warning anywhere. Unknown
     *    brackets are now left exactly as the author typed them, so a
     *    mistyped tag is visible in the composer preview (which runs this
     *    same code) instead of vanishing on the way to the inbox.
     *
     * @param  array<string, mixed>  $data
     */
    protected function replaceShortcodes(string $text, array $data): string
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            // Templates in the wild mix `[name]`, `[Name]` and `[NAME]`.
            $replacements[mb_strtolower((string) $key)] = (string) $value;
        }

        return (string) preg_replace_callback(
            '/\[([a-zA-Z0-9_]+)\]/',
            static fn (array $m): string => $replacements[mb_strtolower($m[1])] ?? $m[0],
            $text,
        );
    }

    protected function processSpintax(string $text, ?int $seed = null): string
    {
        $counter = 0;
        $passes = 0;

        // Innermost-first so nested blocks resolve bottom-up.
        while ($passes++ < self::MAX_PASSES && preg_match('/\{([^{}]+)\}/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $options = explode('|', $matches[1][0]);
            $choice = $seed === null
                ? $options[array_rand($options)]
                : $options[$this->deterministicIndex($seed, $counter++, count($options))];

            // Splice by offset — a regex replace would mangle templates whose
            // options contain regex metacharacters or `$1`-style sequences.
            $text = substr_replace($text, $choice, (int) $matches[0][1], strlen($matches[0][0]));
        }

        return $text;
    }

    private function deterministicIndex(int $seed, int $counter, int $bound): int
    {
        return (int) (hexdec(substr(md5($seed.':'.$counter), 0, 8)) % $bound);
    }
}
