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

    /** @param array<string, mixed> $data */
    protected function replaceShortcodes(string $text, array $data): string
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $replacements['['.$key.']'] = (string) $value;
        }

        // Single pass: a value that itself contains "[Other]" must not be
        // re-expanded (that is how merge-tag injection gets in).
        $text = strtr($text, $this->caseInsensitiveVariants($replacements));

        // Drop any merge tag we could not resolve rather than leaking the raw
        // placeholder into the recipient's inbox.
        return (string) preg_replace('/\[[a-zA-Z0-9_]+\]/', '', $text);
    }

    /**
     * `strtr` is case-sensitive; templates in the wild mix `[name]`/`[Name]`.
     *
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function caseInsensitiveVariants(array $replacements): array
    {
        $expanded = [];

        foreach ($replacements as $tag => $value) {
            $bare = trim($tag, '[]');

            foreach ([$bare, strtolower($bare), strtoupper($bare), ucfirst(strtolower($bare))] as $variant) {
                $expanded['['.$variant.']'] = $value;
            }
        }

        return $expanded;
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
