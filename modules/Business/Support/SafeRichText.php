<?php

declare(strict_types=1);

namespace Modules\Business\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SafeRichText
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a',
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = ['script', 'style', 'template', 'iframe', 'object', 'embed'];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '' || ! preg_match('/<[a-z][^>]*>/i', $html)) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="safe-rich-text-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $root = $document->getElementById('safe-rich-text-root');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public function editorHtml(?string $content): string
    {
        $content = $this->sanitize($content);

        return $this->containsFormatting($content)
            ? $content
            : nl2br(e($content), false);
    }

    public function render(?string $content): string
    {
        return $this->editorHtml($content);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->cleanChildren($node);
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                $this->cleanAttributes($node, $tag);
                $this->cleanChildren($node);
            }

            $node = $next;
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';

        while ($element->attributes->length > 0) {
            $element->removeAttributeNode($element->attributes->item(0));
        }

        if ($tag === 'a' && $this->isSafeHref($href)) {
            $element->setAttribute('href', $href);
        }
    }

    private function isSafeHref(string $href): bool
    {
        return $href !== '' && (bool) preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $href);
    }

    private function containsFormatting(string $content): bool
    {
        return (bool) preg_match('/<(?:'.implode('|', self::ALLOWED_TAGS).')(?:\s|>|\/)/i', $content);
    }
}
