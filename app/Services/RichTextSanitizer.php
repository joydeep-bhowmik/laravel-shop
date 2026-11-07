<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Keeps only a small set of formatting tags so admin-written rich text can be shown on the storefront safely.
 */
class RichTextSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote', 'a'];

    /** @var array<int, string> */
    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Return safe HTML. Disallowed tags are removed with their text kept, and scripts and styles are dropped entirely.
     */
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return null;
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output) === '' ? null : trim($output);
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = Str::lower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                $node->removeChild($child);

                continue;
            }

            // Trix writes paragraphs as divs; storefront markup uses paragraphs.
            if ($tag === 'div') {
                $paragraph = $child->ownerDocument->createElement('p');
                $this->moveChildren($child, $paragraph);
                $node->replaceChild($paragraph, $child);
                $this->cleanChildren($paragraph);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = trim((string) $element->getAttribute('href'));

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($tag !== 'a') {
            return;
        }

        $scheme = Str::lower((string) parse_url($href, PHP_URL_SCHEME));

        if (in_array($scheme, self::ALLOWED_LINK_SCHEMES, true)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'nofollow noopener');
        } else {
            $this->unwrap($element);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    private function moveChildren(DOMNode $from, DOMNode $to): void
    {
        foreach (iterator_to_array($from->childNodes) as $child) {
            $to->appendChild($child);
        }
    }
}
