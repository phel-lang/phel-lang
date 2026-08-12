<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;

use function explode;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function substr;

/**
 * Shared lookup logic for resolving a word under the cursor against the
 * project index. A word can be either `ns/name` (fully qualified) or a bare
 * `name` — the resolver normalises both shapes.
 *
 * Extracted from the four language-feature handlers (`Definition`,
 * `References`, `Rename`, `Hover`) that used to re-implement this by hand.
 *
 * @internal
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
            $parts = array_pad(explode('/', $word, 2), 2, '');
            return [$parts[0], $parts[1]];
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

    /**
     * Resolve a namespace identifier to the location of its `ns` declaration.
     * Namespace metadata may use either Phel's dotted spelling or its internal
     * backslash spelling, so compare a normalised representation.
     */
    public function findNamespace(string $word, ProjectIndex $index): ?Location
    {
        $direct = $index->references[$word . '/'][0] ?? null;
        if ($direct instanceof Location) {
            return $direct;
        }

        $normalizedWord = str_replace('\\', '.', $word);
        foreach ($index->references as $key => $locations) {
            if (!str_ends_with($key, '/')) {
                continue;
            }

            $namespace = substr($key, 0, -1);
            if (str_replace('\\', '.', $namespace) === $normalizedWord) {
                return $locations[0] ?? null;
            }
        }

        return null;
    }
}
