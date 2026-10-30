<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\CacheFilePath;
use Gacela\Framework\Cache\CycleDetectedException;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use ParseError;
use Phel\Build\Domain\Cache\CompiledCodeCacheInterface;
use Phel\Build\Domain\Cache\DependencyTrackerInterface;

use function array_keys;
use function array_pop;
use function in_array;
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
final class DependencyTracker implements DependencyTrackerInterface
{
    use DeferredFlushTrait;

    /** @var ScopedCache<mixed> */
    private readonly ScopedCache $cache;

    /** @var FileCache<mixed> */
    private readonly FileCache $values;

    private readonly ?string $graphFile;

    /** @var array<string, list<string>> */
    private array $dependencies = [];

    public function __construct(string $cacheDir)
    {
        $this->values = new FileCache($cacheDir . '/dependency-graph');
        $this->graphFile = CacheFilePath::inDirectory($this->values->directory, '.gacela-scoped-cache-graph.php');
        // A persistent ScopedCache rewrites its graph for every edge.
        $this->cache = new ScopedCache(new FileCache(''));
        $this->restoreGraph($this->readGraph());
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
        if ($this->values->get($fileKey) !== $file) {
            $this->putValue($fileKey, $file);
        }

        if (!$this->cache->has($nsKey)) {
            $this->putValue($nsKey, $namespace);
        }

        try {
            $this->addDependency($fileKey, $nsKey);
        } catch (CycleDetectedException) {
            // Only a cycle throws here; a duplicate edge is a no-op. The
            // topological sorter already resolves cycles at compile time.
        }

        foreach ($dependencies as $dep) {
            $depKey = $this->nsKey($dep);
            if (!$this->cache->has($depKey)) {
                $this->putValue($depKey, $dep);
            }

            try {
                $this->addDependency($fileKey, $depKey);
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

                $file = $this->values->get($key);
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
        $this->values->clear();
        $this->dependencies = [];
        $this->clearFlushPending();
        if ($this->graphFile !== null) {
            FileCache::delete($this->graphFile);
        }
    }

    public function save(): void
    {
        if (!$this->isFlushPending() || $this->graphFile === null) {
            return;
        }

        $merged = [];
        $written = LockedPhpCacheWriter::write($this->graphFile, function () use (&$merged): array {
            $merged = $this->readGraph();
            foreach ($this->dependencies as $child => $parents) {
                foreach ($parents as $parent) {
                    if (!in_array($parent, $merged[$child] ?? [], true)) {
                        $merged[$child][] = $parent;
                    }
                }
            }

            return $merged;
        });

        if ($written) {
            $this->restoreGraph($merged);
            $this->clearFlushPending();
        }
    }

    private function putValue(string $key, mixed $value): void
    {
        $this->cache->put($key, $value);
        $this->values->put($key, $value);
    }

    private function addDependency(string $child, string $parent): void
    {
        if (in_array($parent, $this->dependencies[$child] ?? [], true)) {
            return;
        }

        $this->cache->dependsOn($child, $parent);
        $this->dependencies[$child][] = $parent;
        $this->markFlushPending();
    }

    /**
     * @param array<string, list<string>> $graph
     */
    private function restoreGraph(array $graph): void
    {
        $this->dependencies = $graph;
        foreach ($graph as $child => $parents) {
            foreach ($parents as $parent) {
                $this->cache->put($parent, substr($parent, 3));
                $this->cache->dependsOn($child, $parent);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function readGraph(): array
    {
        if ($this->graphFile === null) {
            return [];
        }

        if (!is_file($this->graphFile)) {
            return [];
        }

        try {
            $payload = @include $this->graphFile;
        } catch (ParseError) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $graph = [];
        foreach ($payload as $child => $parents) {
            if (!is_string($child) || !str_starts_with($child, 'file:') || !is_array($parents)) {
                continue;
            }

            foreach ($parents as $parent) {
                if (is_string($parent) && str_starts_with($parent, 'ns:') && !in_array($parent, $graph[$child] ?? [], true)) {
                    $graph[$child][] = $parent;
                }
            }
        }

        return $graph;
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
