<?php

namespace App\Services\RichText;

class TipTapDocumentContent
{
    public function hasText(mixed $document): bool
    {
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        if (! is_array($document)) {
            return false;
        }

        return $this->nodeHasText($document);
    }

    /**
     * @param  array<mixed>  $node
     */
    private function nodeHasText(array $node): bool
    {
        if (array_is_list($node)) {
            foreach ($node as $childNode) {
                if (is_array($childNode) && $this->nodeHasText($childNode)) {
                    return true;
                }
            }

            return false;
        }

        if (($node['type'] ?? null) === 'text'
            && is_string($node['text'] ?? null)
            && $this->containsVisibleText($node['text'])) {
            return true;
        }

        $content = $node['content'] ?? null;

        return is_array($content) && $this->nodeHasText($content);
    }

    private function containsVisibleText(string $text): bool
    {
        $normalizedText = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $text);

        return is_string($normalizedText) && $normalizedText !== '';
    }
}
