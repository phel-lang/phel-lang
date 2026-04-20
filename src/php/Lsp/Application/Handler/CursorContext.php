<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;

/**
 * Bundles the four things every "cursor-on-a-word" LSP handler needs:
 *  - the project index (optional for some methods),
 *  - the document referenced by `textDocument/uri`,
 *  - the LSP position payload,
 *  - the identifier under the cursor.
 *
 * Lookup fails closed: if any piece is missing, the value object is `null`
 * and the caller returns the method's empty response. This removes the
 * repeated four-step prelude from `Hover`, `Definition`, `References`, and
 * `Rename`.
 */
final readonly class CursorContext
{
    /**
     * @param array{line: int, character: int} $position
     */
    private function __construct(
        public ProjectIndex $index,
        public Document $document,
        public array $position,
        public string $word,
    ) {}

    /**
     * Resolve everything the cursor-based handlers need, returning `null`
     * if any precondition fails.
     *
     * @param array<string, mixed> $params
     */
    public static function resolve(
        array $params,
        Session $session,
        ParamsExtractor $extractor,
        bool $requireIndex = true,
    ): ?self {
        $index = $session->projectIndex();
        if ($requireIndex && !$index instanceof ProjectIndex) {
            return null;
        }

        $uri = $extractor->uri($params);
        $position = $extractor->position($params);
        if ($uri === '' || $position === null) {
            return null;
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return null;
        }

        $word = $document->wordAt($position);
        if ($word === '') {
            return null;
        }

        return new self(
            $index ?? new ProjectIndex([], []),
            $document,
            $position,
            $word,
        );
    }
}
