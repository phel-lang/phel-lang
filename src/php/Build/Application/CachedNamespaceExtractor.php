<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Iterator;
use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Shared\NamespaceInformation;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use UnexpectedValueException;

use function is_array;

final class CachedNamespaceExtractor implements NamespaceExtractorInterface
{
    private readonly NamespaceFileGrouper $grouper;

    private readonly ExcludedScanPaths $excludedPaths;

    /** @var array<string, list<NamespaceInformation>> */
    private array $directoriesScanCache = [];

    public function __construct(
        private readonly NamespaceExtractorInterface $innerExtractor,
        private readonly NamespaceCacheInterface $cache,
        NamespaceSorterInterface $namespaceSorter,
        ?ExcludedScanPaths $excludedPaths = null,
    ) {
        $this->grouper = new NamespaceFileGrouper($namespaceSorter);
        $this->excludedPaths = $excludedPaths ?? ExcludedScanPaths::none();
    }

    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        $realPath = $this->resolvePath($path);
        if ($realPath === null) {
            return $this->innerExtractor->getNamespaceFromFile($path);
        }

        $cachedEntry = $this->cache->get($realPath);
        if ($cachedEntry instanceof NamespaceCacheEntry && $cachedEntry->isValid()) {
            return $cachedEntry->toNamespaceInformation();
        }

        $info = $this->innerExtractor->getNamespaceFromFile($path);
        $this->cacheNamespaceInfo($info);

        return $info;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        $cacheKey = $this->scanCacheKey($directories);
        if (isset($this->directoriesScanCache[$cacheKey])) {
            return $this->directoriesScanCache[$cacheKey];
        }

        $allInfos = [];
        foreach ($this->findAllPhelFiles($directories) as $file) {
            try {
                $allInfos[] = $this->getNamespaceFromFile($file);
            } catch (ExtractorException) {
                // Skip files that cannot be parsed/lexed so one malformed
                // .phel file in a scanned directory does not abort the
                // whole scan (e.g. REPL starting in a cwd that contains
                // unrelated broken Phel files).
                continue;
            }
        }

        return $this->directoriesScanCache[$cacheKey] = $this->grouper->groupAndSort($allInfos);
    }

    /**
     * @param list<string> $directories
     */
    private function scanCacheKey(array $directories): string
    {
        $resolved = [];
        foreach ($directories as $directory) {
            $real = $this->resolvePath($directory);
            $resolved[] = $real ?? $directory;
        }

        sort($resolved);

        return implode("\0", $resolved);
    }

    private function cacheNamespaceInfo(NamespaceInformation $info): void
    {
        $file = $info->getFile();
        $mtime = @filemtime($file);

        if ($mtime === false) {
            return;
        }

        $entry = new NamespaceCacheEntry(
            $file,
            $mtime,
            $info->getNamespace(),
            $info->getDependencies(),
            $info->isPrimaryDefinition(),
        );

        $this->cache->put($file, $entry);
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function findAllPhelFiles(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $realpath = $this->resolvePath($directory);
            if ($realpath === null) {
                continue;
            }

            if (!is_dir($realpath)) {
                continue;
            }

            try {
                foreach ($this->phelFileIterator($realpath) as $file) {
                    if (!is_array($file)) {
                        continue;
                    }

                    /** @var array<int, string> $file */
                    if ($this->excludedPaths->contains($file[0], $realpath)) {
                        continue;
                    }

                    $resolvedFile = $this->resolvePath($file[0]);
                    if ($resolvedFile !== null) {
                        $files[] = $resolvedFile;
                    }
                }
            } catch (UnexpectedValueException) {
                // Skip directories that cannot be read
                continue;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Build the recursive iterator that yields `.phel`/`.cljc` files under
     * `$root`, pruning subtrees flagged by `ExcludedScanPaths` at descent
     * time so vendor/.git/node_modules never get walked.
     *
     * `RegexIterator::GET_MATCH` yields each path as a `preg_match` result
     * array, so the offset-0 capture holds the matched filename (narrowed at
     * the call site since `RegexIterator`'s generic cannot express it).
     *
     * @return Iterator<mixed, mixed>
     */
    private function phelFileIterator(string $root): Iterator
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );
        $excludedPaths = $this->excludedPaths;
        $prunedDescent = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static function (mixed $current) use ($excludedPaths, $root): bool {
                if (!$current instanceof SplFileInfo || !$current->isDir()) {
                    return true;
                }

                return !$excludedPaths->shouldPruneDirectory(
                    $current->getFilename(),
                    $current->getPathname(),
                    $root,
                );
            },
        );

        $iterator = new RecursiveIteratorIterator($prunedDescent);

        return new RegexIterator(
            $iterator,
            '/^.+\.(phel|cljc)$/i',
            RegexIterator::GET_MATCH,
        );
    }

    private function resolvePath(string $path): ?string
    {
        // Support PHAR paths
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }

        // Normal file system
        $real = realpath($path);
        return $real !== false ? $real : null;
    }
}
