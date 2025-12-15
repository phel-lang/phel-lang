<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Graph\DependencyGraph;
use Phel\Build\Domain\Graph\DependencyGraphCacheInterface;
use Phel\Build\Domain\Graph\FileSetDiff;
use Phel\Build\Domain\Graph\FileSetSnapshot;
use Phel\Build\Domain\Graph\GraphNode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function count;

final readonly class IncrementalNamespaceExtractor implements NamespaceExtractorInterface
{
    public function __construct(
        private NamespaceExtractorInterface $innerExtractor,
        private DependencyGraphCacheInterface $graphCache,
        private FileSetDiffCalculator $diffCalculator,
        private NamespaceSorterInterface $namespaceSorter,
    ) {
    }

    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        return $this->innerExtractor->getNamespaceFromFile($path);
    }

    /**
     * @param list<string> $directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        // Resolve directories to canonical paths for consistent comparison
        $resolvedDirectories = $this->resolveDirectories($directories);

        // 1. Lightweight scan: collect file paths + mtimes only (no parsing)
        $currentFiles = $this->scanFilesWithMtimes($directories);

        // 2. Load cached graph and file set
        $cachedGraph = $this->graphCache->load();
        $cachedFileSet = $this->graphCache->loadFileSet();

        // 3. Validate that cached directories match current directories
        if (!$this->directoriesMatch($cachedFileSet, $resolvedDirectories)) {
            // Directories changed - rebuild from scratch
            $graph = $this->buildFullGraph($directories);
            $fileSet = new FileSetSnapshot($currentFiles, $resolvedDirectories, time());
            $this->graphCache->save($graph, $fileSet);
            return $graph->toNamespaceInformationList();
        }

        // 4. Calculate diff
        $diff = $this->diffCalculator->calculate($cachedFileSet, $currentFiles);

        // 5. If no changes and cache valid, return immediately
        if ($diff->isEmpty() && $cachedGraph instanceof DependencyGraph) {
            return $cachedGraph->toNamespaceInformationList();
        }

        // 6. Build or update graph
        if (!$cachedGraph instanceof DependencyGraph || $this->shouldRebuildFully($diff, $cachedFileSet)) {
            $graph = $this->buildFullGraph($directories);
        } else {
            $graph = $this->updateGraphIncrementally($cachedGraph, $diff);
        }

        // 7. Save and return
        $fileSet = new FileSetSnapshot($currentFiles, $resolvedDirectories, time());
        $this->graphCache->save($graph, $fileSet);

        return $graph->toNamespaceInformationList();
    }

    /**
     * Scan directories for .phel files and collect their paths and mtimes.
     * This is faster than full parsing since we only stat files.
     *
     * @param list<string> $directories
     *
     * @return array<string, int> path => mtime
     */
    private function scanFilesWithMtimes(array $directories): array
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
                        $mtime = @filemtime($resolvedFile);
                        if ($mtime !== false) {
                            $files[$resolvedFile] = $mtime;
                        }
                    }
                }
            } catch (UnexpectedValueException) {
                continue;
            }
        }

        return $files;
    }

    /**
     * Determine if we should rebuild the entire graph instead of updating incrementally.
     * Rebuilding is more efficient when many files have changed.
     */
    private function shouldRebuildFully(FileSetDiff $diff, ?FileSetSnapshot $cached): bool
    {
        if (!$cached instanceof FileSetSnapshot) {
            return true;
        }

        $totalFiles = count($cached->files);
        if ($totalFiles === 0) {
            return true;
        }

        $changedCount = count($diff->added) + count($diff->modified) + count($diff->deleted);

        // Rebuild if more than 50% of files changed
        return ($changedCount / $totalFiles) > 0.5;
    }

    /**
     * Build the dependency graph from scratch.
     *
     * @param list<string> $directories
     */
    private function buildFullGraph(array $directories): DependencyGraph
    {
        // Use the inner extractor which already handles parsing and sorting
        $namespaceInfos = $this->innerExtractor->getNamespacesFromDirectories($directories);

        $nodes = [];
        $topologicalOrder = [];

        foreach ($namespaceInfos as $info) {
            $mtime = @filemtime($info->getFile()) ?: 0;
            $nodes[$info->getNamespace()] = new GraphNode(
                $info->getFile(),
                $info->getNamespace(),
                $mtime,
                $info->getDependencies(),
            );
            $topologicalOrder[] = $info->getNamespace();
        }

        return new DependencyGraph($nodes, $topologicalOrder);
    }

    /**
     * Update the graph incrementally based on file changes.
     */
    private function updateGraphIncrementally(DependencyGraph $cachedGraph, FileSetDiff $diff): DependencyGraph
    {
        $nodes = $cachedGraph->getNodes();

        // Remove deleted files
        foreach ($diff->deleted as $file) {
            $namespace = $this->findNamespaceByFile($nodes, $file);
            if ($namespace !== null) {
                unset($nodes[$namespace]);
            }
        }

        // Update/add changed files
        foreach ($diff->getChangedFiles() as $file) {
            $info = $this->innerExtractor->getNamespaceFromFile($file);
            $mtime = @filemtime($file) ?: 0;

            // Remove old node if namespace changed (e.g., file was renamed)
            $oldNamespace = $this->findNamespaceByFile($nodes, $file);
            if ($oldNamespace !== null && $oldNamespace !== $info->getNamespace()) {
                unset($nodes[$oldNamespace]);
            }

            $nodes[$info->getNamespace()] = new GraphNode(
                $info->getFile(),
                $info->getNamespace(),
                $mtime,
                $info->getDependencies(),
            );
        }

        // Re-sort the graph
        $dependencyIndex = [];
        foreach ($nodes as $namespace => $node) {
            $dependencyIndex[$namespace] = $node->dependencies;
        }

        $topologicalOrder = $this->namespaceSorter->sort(
            array_keys($dependencyIndex),
            $dependencyIndex,
        );

        return new DependencyGraph($nodes, $topologicalOrder);
    }

    /**
     * Find a namespace in the nodes by its file path.
     *
     * @param array<string, GraphNode> $nodes
     */
    private function findNamespaceByFile(array $nodes, string $file): ?string
    {
        foreach ($nodes as $namespace => $node) {
            if ($node->file === $file) {
                return $namespace;
            }
        }

        return null;
    }

    private function resolvePath(string $path): ?string
    {
        // Support PHAR paths
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }

        $real = realpath($path);
        return $real !== false ? $real : null;
    }

    /**
     * Resolve a list of directories to their canonical paths.
     *
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function resolveDirectories(array $directories): array
    {
        $resolved = [];
        foreach ($directories as $directory) {
            $realpath = $this->resolvePath($directory);
            if ($realpath !== null) {
                $resolved[] = $realpath;
            }
        }

        sort($resolved);

        return $resolved;
    }

    /**
     * Check if the cached file set's directories match the current directories.
     *
     * @param list<string> $currentDirectories Resolved/canonical paths
     */
    private function directoriesMatch(?FileSetSnapshot $cached, array $currentDirectories): bool
    {
        if (!$cached instanceof FileSetSnapshot) {
            return false;
        }

        $cachedDirectories = $cached->directories;
        sort($cachedDirectories);

        return $cachedDirectories === $currentDirectories;
    }
}
