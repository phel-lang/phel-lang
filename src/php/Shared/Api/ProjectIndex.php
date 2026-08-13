<?php

declare(strict_types=1);

namespace Phel\Shared\Api;

use Phel\Shared\Munge;

use function array_filter;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;

/**
 * Immutable symbol table keyed by `namespace/name`.
 *
 * Caching hook: a future implementation can key this by file-hash via
 * `ProjectIndexer` so that incremental reindexing avoids redoing parses
 * for unchanged files. For v1 the index is built from scratch each time.
 *
 * @phpstan-import-type SerializedDefinition from Definition
 * @phpstan-import-type SerializedLocation from Location
 */
final readonly class ProjectIndex
{
    /**
     * @param array<string, Definition>     $definitions        keyed by `namespace/name`
     * @param array<string, list<Location>> $references         keyed by `namespace/name`
     * @param array<string, Location>       $namespaceLocations `ns` declaration sites, keyed by canonical (dotted) namespace
     */
    public function __construct(
        public array $definitions,
        public array $references = [],
        public array $namespaceLocations = [],
    ) {}

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        $namespaces = [];
        $seen = [];
        foreach ($this->definitions as $def) {
            $namespaces[] = $def->namespace;
            $seen[Munge::canonicalNs($def->namespace)] = true;
        }

        foreach (array_keys($this->namespaceLocations) as $namespace) {
            // keys are canonical, a definition namespace may spell the same one with backslashes
            if (!isset($seen[$namespace])) {
                $namespaces[] = $namespace;
            }
        }

        return array_values(array_unique($namespaces));
    }

    /**
     * Resolve a namespace identifier to the location of its `ns` declaration.
     * Namespaces reach here in either spelling, so canonicalise before lookup.
     */
    public function namespaceLocation(string $namespace): ?Location
    {
        return $this->namespaceLocations[Munge::canonicalNs($namespace)] ?? null;
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
     *     symbols: array<string, SerializedDefinition>,
     *     references: array<string, list<SerializedLocation>>,
     *     namespaceLocations: array<string, SerializedLocation>,
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

        $namespaceLocations = [];
        foreach ($this->namespaceLocations as $namespace => $location) {
            $namespaceLocations[$namespace] = $location->toArray();
        }

        return [
            'namespaces' => $this->countNamespaces(),
            'definitions' => $this->countDefinitions(),
            'symbols' => $symbols,
            'references' => $references,
            'namespaceLocations' => $namespaceLocations,
        ];
    }
}
