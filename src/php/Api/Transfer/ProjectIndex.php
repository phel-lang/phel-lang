<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function count;

/**
 * Immutable symbol table keyed by `namespace/name`.
 *
 * Caching hook: a future implementation can key this by file-hash via
 * `ProjectIndexer` so that incremental reindexing avoids redoing parses
 * for unchanged files. For v1 the index is built from scratch each time.
 */
final readonly class ProjectIndex
{
    /**
     * @param array<string, Definition>     $definitions keyed by `namespace/name`
     * @param array<string, list<Location>> $references  keyed by `namespace/name`
     */
    public function __construct(
        public array $definitions,
        public array $references = [],
    ) {}

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        $namespaces = [];
        foreach ($this->definitions as $def) {
            $namespaces[] = $def->namespace;
        }

        return array_values(array_unique($namespaces));
    }

    /**
     * @return list<string>
     */
    public function definitionKeys(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * @return list<Definition>
     */
    public function definitionsInNamespace(string $namespace): array
    {
        $result = array_filter(
            $this->definitions,
            static fn(Definition $d): bool => $d->namespace === $namespace,
        );

        return array_values($result);
    }

    public function countDefinitions(): int
    {
        return count($this->definitions);
    }

    public function countNamespaces(): int
    {
        return count($this->namespaces());
    }

    /**
     * @return array{
     *     namespaces: int,
     *     definitions: int,
     *     symbols: array<string, array{
     *         namespace: string,
     *         name: string,
     *         uri: string,
     *         line: int,
     *         col: int,
     *         kind: string,
     *         signature: list<string>,
     *         docstring: string,
     *         private: bool,
     *     }>,
     *     references: array<string, list<array{uri: string, line: int, col: int, endLine: int, endCol: int}>>,
     * }
     */
    public function toArray(): array
    {
        $symbols = [];
        foreach ($this->definitions as $key => $def) {
            $symbols[$key] = $def->toArray();
        }

        $references = [];
        foreach ($this->references as $key => $locations) {
            $references[$key] = array_map(
                static fn(Location $loc): array => $loc->toArray(),
                $locations,
            );
        }

        return [
            'namespaces' => $this->countNamespaces(),
            'definitions' => $this->countDefinitions(),
            'symbols' => $symbols,
            'references' => $references,
        ];
    }
}
