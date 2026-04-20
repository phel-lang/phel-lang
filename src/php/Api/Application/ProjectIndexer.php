<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\ProjectIndexerInterface;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function file_get_contents;
use function is_dir;
use function realpath;

/**
 * Walks a list of source directories, reads every .phel file and aggregates
 * Definitions and references into a ProjectIndex.
 *
 * Caching hook: results could be keyed on a file-hash -> Definition mapping and
 * stored under `.phel/api-index-cache/`. For v1 we re-index from scratch.
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

            foreach ($this->iteratePhelFiles($real) as $file) {
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                $result = $this->extractor->extract($contents, $file);

                foreach ($result['definitions'] as $definition) {
                    $definitions[$definition->fullName()] = $definition;
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

    /**
     * @return iterable<string>
     */
    private function iteratePhelFiles(string $directory): iterable
    {
        try {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException) {
            return [];
        }

        foreach ($regex as $match) {
            yield $match[0];
        }
    }
}
