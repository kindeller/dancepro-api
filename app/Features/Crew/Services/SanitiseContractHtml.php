<?php

namespace App\Features\Crew\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class SanitiseContractHtml
{
    private const ALLOWED_TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a'];

    public function execute(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        return trim(collect(iterator_to_array($root->childNodes))->map(fn (DOMNode $node): string => $document->saveHTML($node))->join(''));
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $this->cleanChildren($node);
            $tag = strtolower($node->tagName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $parent->removeChild($node);

                    continue;
                }
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $href = $tag === 'a' ? $node->getAttribute('href') : null;
            while ($node->attributes->length > 0) {
                $node->removeAttributeNode($node->attributes->item(0));
            }
            if ($href && preg_match('/^(https?:|mailto:)/i', $href)) {
                $node->setAttribute('href', $href);
                $node->setAttribute('rel', 'noopener');
            }
        }
    }
}
