<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\CycleDetectedException;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use Phel\Build\Domain\Cache\CompiledCodeCacheInterface;
use Phel\Build\Domain\Cache\DependencyTrackerInterface;

use function array_keys;
use function array_pop;
use function is_array;
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
 *
 * @internal
 */
final readonly class DependencyTracker implements DependencyTrackerInterface
{
    /** @var ScopedCache<mixed> */
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

        $file = ['source' => $sourcePath, 'namespace' => $namespace];
        if ($this->cache->get($fileKey) !== $file) {
            $this->cache->put($fileKey, $file);
        }

        if (!$this->cache->has($nsKey)) {
            $this->cache->put($nsKey, $namespace);
        }

        try {
            $this->cache->dependsOn($fileKey, $nsKey);
        } catch (CycleDetectedException) {
            // Only a cycle throws here; a duplicate edge is a no-op. The
            // topological sorter already resolves cycles at compile time.
        }

        foreach ($dependencies as $dep) {
            $depKey = $this->nsKey($dep);
            if (!$this->cache->has($depKey)) {
                $this->cache->put($depKey, $dep);
            }

            try {
                $this->cache->dependsOn($fileKey, $depKey);
            } catch (CycleDetectedException) {
                // Only a cycle throws here, and it is already resolved at
                // compile time by the topological sorter.
            }
        }
    }

    /**
     * Invalidate all compiled files that depend on the given namespace.
     * Returns the list of source paths whose cache entries were invalidated.
     *
     * @return list<string> Source paths of invalidated dependents
     */
    public function invalidateDependentsOf(string $namespace, CompiledCodeCacheInterface $compiledCodeCache): array
    {
        $nsKey = $this->nsKey($namespace);
        if (!$this->cache->has($nsKey)) {
            return [];
        }

        $pending = [$nsKey];
        $visited = [];
        $invalidated = [];

        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;
            foreach ($this->cache->dependents($current) as $key) {
                if (!str_starts_with($key, 'file:')) {
                    continue;
                }

                $file = $this->cache->get($key);
                if (!is_array($file)) {
                    continue;
                }

                $sourcePath = $file['source'] ?? null;
                $definedNamespace = $file['namespace'] ?? null;
                if (!is_string($sourcePath) || !is_string($definedNamespace)) {
                    continue;
                }

                if (isset($invalidated[$sourcePath])) {
                    continue;
                }

                $compiledCodeCache->invalidate($sourcePath);
                $invalidated[$sourcePath] = true;
                $pending[] = $this->nsKey($definedNamespace);
            }
        }

        return array_keys($invalidated);
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
