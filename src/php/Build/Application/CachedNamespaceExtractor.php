<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
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

    /** @var list<string> */
    private array $excludedPrefixes;

    /**
     * @param list<string> $excludedDirectories absolute paths whose subtree must be
     *                                          skipped during recursive namespace scanning
     * @param string       $destDirBasename     when non-empty, any `<scan_root>/<basename>/`
     *                                          subtree is skipped per walk
     */
    public function __construct(
        private NamespaceExtractorInterface $innerExtractor,
        private NamespaceCacheInterface $cache,
        NamespaceSorterInterface $namespaceSorter,
        array $excludedDirectories = [],
        private string $destDirBasename = '',
    ) {
        $this->grouper = new NamespaceFileGrouper($namespaceSorter);
        $this->excludedPrefixes = $this->normalizeExcludedPrefixes($excludedDirectories);
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
            $allInfos[] = $this->getNamespaceFromFile($file);
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

            $walkExcludedPrefixes = $this->excludedPrefixesForWalk($realpath);

            try {
                $directoryIterator = new RecursiveDirectoryIterator($realpath);
                $iterator = new RecursiveIteratorIterator($directoryIterator);
                $phelIterator = new RegexIterator($iterator, '/^.+\.(phel|cljc)$/i', RegexIterator::GET_MATCH);

                foreach ($phelIterator as $file) {
                    if ($this->isExcluded($file[0], $walkExcludedPrefixes)) {
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

    /**
     * @param list<string> $walkPrefixes
     */
    private function isExcluded(string $path, array $walkPrefixes): bool
    {
        foreach ($walkPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function excludedPrefixesForWalk(string $scanRoot): array
    {
        $prefixes = $this->excludedPrefixes;

        if ($this->destDirBasename !== '') {
            $prefixes[] = rtrim($scanRoot, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $this->destDirBasename
                . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function normalizeExcludedPrefixes(array $directories): array
    {
        $prefixes = [];
        foreach ($directories as $dir) {
            if ($dir === '') {
                continue;
            }

            $real = realpath($dir);
            $resolved = $real !== false ? $real : $dir;
            $prefixes[] = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }
}
