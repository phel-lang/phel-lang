<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Document;

use Phel\Lsp\Application\Rpc\ParamsExtractor;

use function is_array;
use function is_string;

/**
 * Applies the raw `contentChanges` array from an LSP `textDocument/didChange`
 * request to a {@see Document}. Handles both the full-document form and the
 * incremental range form, then bumps the document version to the request's
 * value.
 *
 * Isolating this from `DidChangeHandler` lets the handler stay focused on
 * transport and debounced diagnostic publishing, and keeps the mutation rules
 * independently testable.
 */
final readonly class ContentChangeApplier
{
    public function __construct(private ParamsExtractor $params) {}

    /**
     * @param mixed $contentChanges raw value from the `params['contentChanges']` slot
     *
     * @return bool true if a mutation was applied, false if the payload was invalid
     */
    public function apply(Document $document, mixed $contentChanges, int $version): bool
    {
        if (!is_array($contentChanges)) {
            return false;
        }

        foreach ($contentChanges as $change) {
            if (!is_array($change)) {
                continue;
            }

            $text = is_string($change['text'] ?? null) ? $change['text'] : '';
            $range = $change['range'] ?? null;

            if (is_array($range) && $this->params->isValidRange($range)) {
                /** @var array{start: array{line: int, character: int}, end: array{line: int, character: int}} $range */
                $document->applyRange($range, $text);
            } else {
                $document->update($text, $version);
            }
        }

        $document->bumpVersion($version);
        return true;
    }
}
