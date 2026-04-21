<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

final readonly class CachedNamespaceExtractor implements NamespaceExtractorInterface
{
    private NamespaceFileGrouper $grouper;

    private ExcludedScanPaths $excludedPaths;

    public function __construct(
        private NamespaceExtractorInterface $innerExtractor,
        private NamespaceCacheInterface $cache,
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

        return $this->grouper->groupAndSort($allInfos);
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
                $directoryIterator = new RecursiveDirectoryIterator($realpath);
                $iterator = new RecursiveIteratorIterator($directoryIterator);
                $phelIterator = new RegexIterator($iterator, '/^.+\.(phel|cljc)$/i', RegexIterator::GET_MATCH);

                foreach ($phelIterator as $file) {
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

        return array_unique($files);
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
