<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Graph;

interface DependencyGraphCacheInterface
{
    /**
     * Load the cached dependency graph.
     * Returns null if cache is missing or invalid.
     */
    public function load(): ?DependencyGraph;

    /**
     * Load the cached file set snapshot.
     * Returns null if cache is missing or invalid.
     */
    public function loadFileSet(): ?FileSetSnapshot;

    /**
     * Save the dependency graph and file set to cache.
     */
    public function save(DependencyGraph $graph, FileSetSnapshot $fileSet): void;

    /**
     * Clear all cached data.
     */
    public function clear(): void;
}
