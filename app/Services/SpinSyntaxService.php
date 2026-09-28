<?php

namespace App\Services;

class SpinSyntaxService
{
    /**
     * Parses a spintax string like "{Hello|Hi} [Name]" and replaces variables.
     * 
     * @param string $text The raw template
     * @param array $data Variables to replace (e.g. ['Name' => 'John'])
     * @return string
     */
    public function compile(string $text, array $data = []): string
    {
        // First replace basic shortcodes like [Name]
        $compiled = $this->replaceShortcodes($text, $data);

        // Then process spintax recursively
        return $this->processSpintax($compiled);
    }

    protected function replaceShortcodes(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            // Support [Name] or {Name} or %Name% formats if we want, but sticking to [Name] for now
            $text = str_ireplace('[' . $key . ']', $value, $text);
        }

        // Clean up any unreplaced shortcodes
        $text = preg_replace('/\[[a-zA-Z0-9_]+\]/', '', $text);

        return $text;
    }

    protected function processSpintax(string $text): string
    {
        // Use regex to find the innermost {a|b|c} block
        // {([^{}]*)} matches a block with no inner curlies
        while (preg_match('/\{([^{}]+)\}/', $text, $matches)) {
            $options = explode('|', $matches[1]);
            $randomOption = $options[array_rand($options)];
            // Replace the exact matched block (including curlies) with the chosen option
            $text = preg_replace('/' . preg_quote($matches[0], '/') . '/', $randomOption, $text, 1);
        }

        return $text;
    }
}