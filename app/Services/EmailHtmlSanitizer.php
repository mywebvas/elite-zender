<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Strips anything dangerous out of operator-authored campaign HTML.
 *
 * This is not paranoia about the operator — it is about everyone downstream.
 * Campaign HTML is rendered back into the workspace UI (preview, campaign
 * detail), so an injected `<script>` or `onerror=` is stored XSS against the
 * operator's own colleagues, and a compromised or malicious workspace member
 * could otherwise exfiltrate session data from an admin viewing the campaign.
 *
 * The previous pipeline passed the WYSIWYG output through verbatim.
 *
 * An allow-list is used deliberately: a deny-list of "bad tags" is a game you
 * lose the first time a new element ships in a browser.
 */
final class EmailHtmlSanitizer
{
    /** Elements an email may legitimately contain. */
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'center', 'code', 'div', 'em', 'h1', 'h2',
        'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's',
        'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** Attributes allowed on any element. */
    private const GLOBAL_ATTRIBUTES = ['class', 'style', 'title', 'dir', 'lang'];

    /** Extra attributes allowed per element. */
    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => ['width', 'align', 'border', 'cellpadding', 'cellspacing', 'role'],
        'td' => ['width', 'align', 'valign', 'colspan', 'rowspan', 'bgcolor'],
        'th' => ['width', 'align', 'valign', 'colspan', 'rowspan', 'bgcolor'],
        'tr' => ['align', 'valign', 'bgcolor'],
    ];

    /** URL schemes a link or image may use. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** CSS properties that survive inlining and are safe. */
    private const ALLOWED_CSS = [
        'background-color', 'border', 'border-bottom', 'border-collapse',
        'border-color', 'border-left', 'border-radius', 'border-right',
        'border-top', 'border-width', 'color', 'display', 'font-family',
        'font-size', 'font-style', 'font-weight', 'height', 'letter-spacing',
        'line-height', 'margin', 'margin-bottom', 'margin-left', 'margin-right',
        'margin-top', 'max-width', 'min-width', 'padding', 'padding-bottom',
        'padding-left', 'padding-right', 'padding-top', 'text-align',
        'text-decoration', 'text-transform', 'vertical-align', 'width',
        'white-space', 'word-break',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = $this->load($html);
        $xpath = new DOMXPath($document);

        // Comments can carry conditional-comment payloads; drop them all.
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $this->clean($body);

        return $this->innerHtml($body);
    }

    private function load(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        // The meta charset prevents DOMDocument mangling non-ASCII content
        // into HTML entities, which would corrupt every non-English campaign.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function clean(DOMNode $node): void
    {
        // Iterate over a snapshot: removing children mutates the live NodeList.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap rather than delete, so a stray <font> or <section>
                // does not silently swallow the operator's copy. Script and
                // style are the exception: their text content is the payload.
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'], true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                $this->clean($child);
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
            $this->clean($child);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = [...self::GLOBAL_ATTRIBUTES, ...(self::TAG_ATTRIBUTES[$tag] ?? [])];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            // Every on* handler, plus anything not explicitly allowed.
            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'style') {
                $filtered = $this->filterStyle($attribute->nodeValue ?? '');

                $filtered === ''
                    ? $element->removeAttribute('style')
                    : $element->setAttribute('style', $filtered);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // Links that open a new tab must not hand the opener to the target.
        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function filterStyle(string $style): string
    {
        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);

            $property = strtolower(trim($property));
            $value = trim($value);

            if (! in_array($property, self::ALLOWED_CSS, true)) {
                continue;
            }

            // url() can smuggle javascript:, and expression() is a legacy IE
            // script vector that still appears in payload lists.
            if (preg_match('/url\s*\(|expression\s*\(|javascript:|@import/i', $value) === 1) {
                continue;
            }

            $safe[] = "{$property}: {$value}";
        }

        return implode('; ', $safe);
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Merge tags and anchors resolve later; let them through untouched.
        if (str_starts_with($url, '#') || str_starts_with($url, '[') || str_starts_with($url, '{')) {
            return true;
        }

        // Relative URLs are fine; they are resolved against the sending domain.
        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches)) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    /** Replace an element with its own children. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim(self::restoreMergeTagDelimiters($html));
    }

    /**
     * `DOMDocument::saveHTML()` percent-encodes URL-unsafe characters inside
     * href/src, which mangles `[Name]` into `%5BName%5D` and `{a|b}` into
     * `%7Ba%7Cb%7D`. Personalised and spun links are a core feature, so those
     * four delimiters (plus the spintax pipe) are decoded again.
     *
     * This cannot reintroduce markup: none of these characters can open a tag
     * or an attribute.
     */
    public static function restoreMergeTagDelimiters(string $html): string
    {
        return str_ireplace(
            ['%5B', '%5D', '%7B', '%7D', '%7C'],
            ['[', ']', '{', '}', '|'],
            $html,
        );
    }
}
