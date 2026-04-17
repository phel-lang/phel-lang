<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\CycleDetectedException;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;

use function is_string;

/**
 * Tracks namespace-level dependencies between Phel source files
 * using ScopedCache's dependency graph. When a namespace changes,
 * all transitive dependents are automatically invalidated.
 *
 * Keys use a prefix convention:
 * - "ns:{namespace}" — represents a namespace (the parent)
 * - "file:{sourcePath}" — represents a compiled source file (the child)
 *
 * A file that requires namespace B is registered as:
 *   dependsOn("file:{sourcePath}", "ns:B")
 */
final readonly class DependencyTracker
{
    private ScopedCache $cache;

    public function __construct(string $cacheDir)
    {
        $this->cache = new ScopedCache(
            new FileCache($cacheDir . '/dependency-graph'),
        );
    }

    /**
     * Register that a source file depends on the given namespaces.
     *
     * @param string       $sourcePath   Absolute path to the .phel source file
     * @param string       $namespace    The namespace this file defines
     * @param list<string> $dependencies Namespaces this file requires
     */
    public function registerDependencies(string $sourcePath, string $namespace, array $dependencies): void
    {
        $fileKey = $this->fileKey($sourcePath);
        $nsKey = $this->nsKey($namespace);

        if (!$this->cache->has($fileKey)) {
            $this->cache->put($fileKey, $sourcePath);
        }

        if (!$this->cache->has($nsKey)) {
            $this->cache->put($nsKey, $namespace);
        }

        // File belongs to its namespace
        try {
            $this->cache->dependsOn($fileKey, $nsKey);
        } catch (CycleDetectedException) {
            // Edge already exists or would create cycle — skip
        }

        foreach ($dependencies as $dep) {
            $depKey = $this->nsKey($dep);
            if (!$this->cache->has($depKey)) {
                $this->cache->put($depKey, $dep);
            }

            try {
                $this->cache->dependsOn($fileKey, $depKey);
            } catch (CycleDetectedException) {
                // Skip circular dependencies — the topological sorter
                // already handles these at compile time.
            }
        }
    }

    /**
     * Invalidate all compiled files that depend on the given namespace.
     * Returns the list of source paths whose cache entries were invalidated.
     *
     * @return list<string> Source paths of invalidated dependents
     */
    public function invalidateDependentsOf(string $namespace, CompiledCodeCache $compiledCodeCache): array
    {
        $nsKey = $this->nsKey($namespace);
        if (!$this->cache->has($nsKey)) {
            return [];
        }

        $dependents = $this->cache->dependents($nsKey);
        $invalidated = [];

        foreach ($dependents as $key) {
            if (!str_starts_with($key, 'file:')) {
                continue;
            }

            $sourcePath = $this->cache->get($key);
            if (!is_string($sourcePath)) {
                continue;
            }

            $compiledCodeCache->invalidate($sourcePath);
            $invalidated[] = $sourcePath;
        }

        return $invalidated;
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    private function fileKey(string $sourcePath): string
    {
        return 'file:' . $sourcePath;
    }

    private function nsKey(string $namespace): string
    {
        return 'ns:' . $namespace;
    }
}
