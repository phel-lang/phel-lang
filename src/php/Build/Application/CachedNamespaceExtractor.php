<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function count;
use function sprintf;

final readonly class CachedNamespaceExtractor implements NamespaceExtractorInterface
{
    public function __construct(
        private NamespaceExtractorInterface $innerExtractor,
        private NamespaceCacheInterface $cache,
        private NamespaceSorterInterface $namespaceSorter,
    ) {
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
        $allFiles = $this->findAllPhelFiles($directories);

        /** @var array<string, NamespaceInformation> $namespaces */
        $namespaces = [];
        /** @var array<string, list<string>> $primaryDefinitions */
        $primaryDefinitions = [];

        foreach ($allFiles as $file) {
            $info = $this->getNamespaceFromFile($file);
            $namespace = $info->getNamespace();
            if ($info->isPrimaryDefinition()) {
                $primaryDefinitions[$namespace][] = $info->getFile();
            }

            $namespaces[$namespace] = $info;
        }

        $this->warnAboutDuplicateNamespaces($primaryDefinitions);

        return $this->sortNamespaceInformationList(array_values($namespaces));
    }

    /**
     * @param array<string, list<string>> $allLocations
     */
    private function warnAboutDuplicateNamespaces(array $allLocations): void
    {
        foreach ($allLocations as $namespace => $files) {
            if (count($files) > 1) {
                $fileList = implode("\n", array_map(static fn (string $f): string => '  - ' . $f, $files));
                fwrite(STDERR, sprintf(
                    "\nWARNING: Namespace '%s' is defined in multiple locations:\n%s\n" .
                    "The last one will be used. Check your phel-config.php srcDirs/testDirs settings.\n",
                    $namespace,
                    $fileList,
                ));
            }
        }
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
                $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);

                foreach ($phelIterator as $file) {
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

    /**
     * @param list<NamespaceInformation> $namespaceInformationList
     *
     * @return list<NamespaceInformation>
     */
    private function sortNamespaceInformationList(array $namespaceInformationList): array
    {
        $dependencyIndex = [];
        $infoIndex = [];

        foreach ($namespaceInformationList as $info) {
            $dependencyIndex[$info->getNamespace()] = $info->getDependencies();
            $infoIndex[$info->getNamespace()] = $info;
        }

        $orderedNamespaces = $this->namespaceSorter->sort(array_keys($dependencyIndex), $dependencyIndex);

        $result = [];
        foreach ($orderedNamespaces as $namespace) {
            if (isset($infoIndex[$namespace])) {
                $result[] = $infoIndex[$namespace];
            }
        }

        return $result;
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
