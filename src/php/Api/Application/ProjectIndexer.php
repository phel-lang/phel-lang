<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\ProjectIndexerInterface;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Munge;

use function file_get_contents;
use function is_dir;
use function realpath;

/**
 * Walks a list of source directories, reads every .phel file and aggregates
 * Definitions and references into a ProjectIndex.
 *
 * Caching hook: results could be keyed on a file-hash -> Definition mapping and
 * stored under `.phel/api-index-cache/`. For v1 we re-index from scratch.
 *
 * @internal
 */
final readonly class ProjectIndexer implements ProjectIndexerInterface
{
    /**
     * @param list<string> $excludedDirs directory names, relative to each
     *                                   indexed root, whose subtrees are skipped
     */
    public function __construct(
        private SymbolExtractor $extractor,
        private array $excludedDirs = [],
    ) {}

    public function index(array $srcDirs): ProjectIndex
    {
        /** @var array<string, Definition> $definitions */
        $definitions = [];
        /** @var array<string, list<Location>> $references */
        $references = [];
        /** @var array<string, Location> $namespaceLocations */
        $namespaceLocations = [];

        foreach ($srcDirs as $dir) {
            $real = realpath($dir);
            if ($real === false) {
                continue;
            }

            if (!is_dir($real)) {
                continue;
            }

            foreach (PhelFileIterator::iterate($real, $this->excludedPrefixesFor($real)) as $file) {
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                $result = $this->extractor->extract($contents, $file);

                foreach ($result['definitions'] as $definition) {
                    $definitions[$definition->fullName()] = $definition;
                }

                if ($result['namespace'] !== '' && $result['namespaceLocation'] instanceof Location) {
                    $namespaceLocations[Munge::canonicalNs($result['namespace'])] = $result['namespaceLocation'];
                }

                foreach ($result['references'] as $key => $locations) {
                    if (!isset($references[$key])) {
                        $references[$key] = [];
                    }

                    foreach ($locations as $location) {
                        $references[$key][] = $location;
                    }
                }
            }
        }

        return new ProjectIndex($definitions, $references, $namespaceLocations);
    }

    /**
     * The excluded directory names resolved against one root, as absolute path
     * prefixes. A name that does not exist under this root contributes nothing.
     *
     * @return list<string>
     */
    private function excludedPrefixesFor(string $root): array
    {
        $prefixes = [];
        foreach ($this->excludedDirs as $dir) {
            $prefixes[] = $root . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }
}
