<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns WYSIWYG output into HTML that email clients actually render.
 *
 * The composer previously handed Quill's raw output straight to the send
 * pipeline. Quill styles content with *classes* (`ql-align-center`,
 * `ql-size-large`, `ql-indent-1`) and relies on a stylesheet that is never
 * delivered with the message. Outlook also drops `<style>` blocks entirely and
 * needs table-based layout. The practical result: every campaign arrived
 * left-aligned, unstyled and structurally broken.
 *
 * This renderer:
 *   1. maps the editor's classes to inline styles,
 *   2. inlines a baseline typographic style on every block element,
 *   3. wraps the content in the centred table scaffold that Outlook requires,
 *   4. prepends a hidden preheader.
 */
final class EmailHtmlRenderer
{
    /** Quill class → inline declarations. */
    private const CLASS_STYLES = [
        'ql-align-center' => 'text-align: center;',
        'ql-align-right' => 'text-align: right;',
        'ql-align-justify' => 'text-align: justify;',
        'ql-indent-1' => 'padding-left: 3em;',
        'ql-indent-2' => 'padding-left: 6em;',
        'ql-indent-3' => 'padding-left: 9em;',
        'ql-size-small' => 'font-size: 13px;',
        'ql-size-large' => 'font-size: 20px;',
        'ql-size-huge' => 'font-size: 28px;',
        'ql-font-serif' => "font-family: Georgia, 'Times New Roman', serif;",
        'ql-font-monospace' => "font-family: 'SFMono-Regular', Menlo, Consolas, monospace;",
    ];

    /** Baseline inline styling per tag — email clients reset almost everything. */
    private const TAG_STYLES = [
        'p' => 'margin: 0 0 16px 0; line-height: 1.6;',
        'h1' => 'margin: 0 0 16px 0; font-size: 28px; line-height: 1.25; font-weight: 700;',
        'h2' => 'margin: 0 0 14px 0; font-size: 22px; line-height: 1.3; font-weight: 700;',
        'h3' => 'margin: 0 0 12px 0; font-size: 18px; line-height: 1.4; font-weight: 600;',
        'h4' => 'margin: 0 0 12px 0; font-size: 16px; line-height: 1.4; font-weight: 600;',
        'ul' => 'margin: 0 0 16px 0; padding-left: 24px;',
        'ol' => 'margin: 0 0 16px 0; padding-left: 24px;',
        'li' => 'margin: 0 0 6px 0; line-height: 1.6;',
        'blockquote' => 'margin: 0 0 16px 0; padding: 8px 0 8px 16px; border-left: 4px solid #e2e8f0; color: #475569; font-style: italic;',
        'pre' => 'margin: 0 0 16px 0; padding: 12px; background-color: #f1f5f9; border-radius: 6px; font-family: monospace; font-size: 13px; white-space: pre-wrap; word-break: break-word;',
        'a' => 'color: #4f46e5; text-decoration: underline;',
        'img' => 'max-width: 100%; height: auto; display: block; border: 0;',
        'hr' => 'border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;',
        'table' => 'border-collapse: collapse;',
    ];

    private const FONT_STACK = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    public function __construct(
        private readonly EmailHtmlSanitizer $sanitizer,
    ) {}

    /**
     * Render editor HTML into a complete, email-safe document.
     */
    public function render(string $editorHtml, ?string $preheader = null): string
    {
        $content = $this->inlineStyles($this->sanitizer->sanitize($editorHtml));

        return $this->wrap($content, $preheader);
    }

    /**
     * Render only the body fragment — used by the in-app preview, which is
     * already inside a styled container.
     */
    public function renderFragment(string $editorHtml): string
    {
        return $this->inlineStyles($this->sanitizer->sanitize($editorHtml));
    }

    private function inlineStyles(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        /** @var iterable<DOMElement> $elements */
        $elements = $xpath->query('//body//*') ?: [];

        foreach ($elements as $element) {
            $declarations = [];

            $tag = strtolower($element->nodeName);

            if (isset(self::TAG_STYLES[$tag])) {
                $declarations[] = self::TAG_STYLES[$tag];
            }

            foreach (preg_split('/\s+/', $element->getAttribute('class')) ?: [] as $class) {
                if (isset(self::CLASS_STYLES[$class])) {
                    $declarations[] = self::CLASS_STYLES[$class];
                }
            }

            // Author styles win over our baseline, so an explicit colour or
            // alignment set in the editor is never overridden.
            $existing = trim($element->getAttribute('style'));

            if ($existing !== '') {
                $declarations[] = rtrim($existing, ';').';';
            }

            if ($declarations !== []) {
                $element->setAttribute('style', trim(implode(' ', $declarations)));
            }

            // The class has served its purpose and only adds weight now.
            $element->removeAttribute('class');

            // Images without alt text are read aloud as their filename and
            // look broken when images are blocked (the Outlook default).
            if ($tag === 'img' && $element->getAttribute('alt') === '') {
                $element->setAttribute('alt', '');
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $out = '';

        foreach ($body === null ? [] : $body->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim(EmailHtmlSanitizer::restoreMergeTagDelimiters($out));
    }

    /**
     * The classic centred-table scaffold.
     *
     * A bare `<div style="max-width:600px;margin:auto">` is ignored by Outlook
     * (Word's rendering engine), which is why every serious ESP still ships
     * tables in 2026.
     */
    private function wrap(string $content, ?string $preheader): string
    {
        $font = self::FONT_STACK;
        $preheaderBlock = '';

        if ($preheader !== null && trim($preheader) !== '') {
            $text = e(trim($preheader));

            // Hidden from view but read by the inbox list. The trailing
            // zero-width spaces stop the client padding the snippet with the
            // first words of the visible body.
            $preheaderBlock = <<<HTML
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #ffffff; opacity: 0;">
        {$text}
        {$this->snippetPadding()}
    </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="x-apple-disable-message-reformatting" />
<title></title>
<!--[if mso]>
<style type="text/css">body, table, td, span, a { font-family: Arial, Helvetica, sans-serif !important; }</style>
<![endif]-->
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f1f5f9; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
{$preheaderBlock}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; background-color: #f1f5f9;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 600px; max-width: 100%; background-color: #ffffff; border-radius: 8px;">
                    <tr>
                        <td style="padding: 32px; font-family: {$font}; font-size: 16px; line-height: 1.6; color: #0f172a;">
{$content}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function snippetPadding(): string
    {
        return str_repeat('&#8199;&#65279;&#847; ', 30);
    }

    /**
     * Derive a readable plain-text alternative from the HTML.
     *
     * Every message must carry one: a missing text/plain part is a well-known
     * spam signal, and it is what accessibility tooling and watch faces read.
     */
    public function toPlainText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = preg_replace('#<(br|/p|/h[1-6]|/li|/tr|hr)[^>]*>#i', "\n", $html) ?? $html;
        $text = preg_replace('#<li[^>]*>#i', '• ', $text) ?? $text;

        // Surface link targets, which are otherwise lost entirely. Parentheses
        // rather than angle brackets: the strip_tags() below would read
        // `<https://…>` as a tag and delete the URL we just recovered.
        $text = preg_replace_callback(
            '#<a[^>]+href=([\'"])(.*?)\1[^>]*>(.*?)</a>#is',
            function (array $m): string {
                $label = trim(strip_tags($m[3]));
                $href = trim($m[2]);

                return $label === '' || $label === $href ? $href : "{$label} ({$href})";
            },
            $text,
        ) ?? $text;

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim(implode("\n", array_map('trim', explode("\n", $text))));
    }
}
