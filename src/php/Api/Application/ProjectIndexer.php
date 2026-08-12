<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\ProjectIndexerInterface;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;

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
    public function __construct(
        private SymbolExtractor $extractor,
    ) {}

    public function index(array $srcDirs): ProjectIndex
    {
        /** @var array<string, Definition> $definitions */
        $definitions = [];
        /** @var array<string, list<Location>> $references */
        $references = [];

        foreach ($srcDirs as $dir) {
            $real = realpath($dir);
            if ($real === false) {
                continue;
            }

            if (!is_dir($real)) {
                continue;
            }

            foreach (PhelFileIterator::iterate($real) as $file) {
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                $result = $this->extractor->extract($contents, $file);

                foreach ($result['definitions'] as $definition) {
                    $definitions[$definition->fullName()] = $definition;
                }

                if ($result['namespace'] !== '' && $result['namespaceLocation'] instanceof Location) {
                    $references[$result['namespace'] . '/'] = [$result['namespaceLocation']];
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

        return new ProjectIndex($definitions, $references);
    }
}
