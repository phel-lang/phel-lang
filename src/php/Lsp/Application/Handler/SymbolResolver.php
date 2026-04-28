<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;

use function explode;
use function str_contains;

/**
 * Shared lookup logic for resolving a word under the cursor against the
 * project index. A word can be either `ns/name` (fully qualified) or a bare
 * `name` — the resolver normalises both shapes.
 *
 * Extracted from the four language-feature handlers (`Definition`,
 * `References`, `Rename`, `Hover`) that used to re-implement this by hand.
 */
final class SymbolResolver
{
    /**
     * Split a cursor word into `[namespace, name]`. For bare names we fall
     * back to scanning the index so the caller can still issue a reference
     * query against the right namespace.
     *
     * @return array{0: string, 1: string}
     */
    public function split(string $word, ProjectIndex $index): array
    {
        if (str_contains($word, '/')) {
            $parts = explode('/', $word, 2);
            return [$parts[0], $parts[1] ?? ''];
        }

        foreach ($index->definitions as $def) {
            if ($def->name === $word) {
                return [$def->namespace, $def->name];
            }
        }

        return ['', $word];
    }

    /**
     * Resolve a word to a definition, preferring fully qualified
     * `ns/name` lookup but falling through to bare-name scans.
     */
    public function find(string $word, ProjectIndex $index): ?Definition
    {
        if (str_contains($word, '/')) {
            return $index->definitions[$word] ?? null;
        }

        foreach ($index->definitions as $def) {
            if ($def->name === $word) {
                return $def;
            }
        }

        return null;
    }
}
