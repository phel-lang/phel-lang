<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\Cache\DependencyTracker;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class DependencyTrackerTest extends TestCase
{
    use RemoveDirTrait;

    private string $cacheDir;

    /** @var list<DependencyTracker> */
    private array $trackers = [];

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-dep-tracker-test-' . uniqid();
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->trackers as $tracker) {
            $tracker->save();
        }

        $this->removeDir($this->cacheDir);
    }

    public function test_fifty_namespaces_write_the_graph_once_at_flush(): void
    {
        $tracker = $this->tracker();
        $graphFile = $this->cacheDir . '/dependency-graph/.gacela-scoped-cache-graph.php';
        $writes = 0;
        $previous = false;
        for ($index = 0; $index < 50; ++$index) {
            $tracker->registerDependencies('/sources/ns' . $index . '.phel', 'app.ns' . $index, ['phel.core']);
            $payload = is_file($graphFile) ? file_get_contents($graphFile) : false;
            if ($payload !== $previous) {
                ++$writes;
                $previous = $payload;
            }
        }

        self::assertSame(0, $writes, 'Graph writes must be deferred until flush');
        $tracker->save();
        self::assertFileExists($graphFile);
        if (file_get_contents($graphFile) !== $previous) {
            ++$writes;
        }

        self::assertSame(1, $writes);
        self::assertCount(50, include $graphFile);
        touch($graphFile, 1);
        $tracker->save();
        clearstatcache();
        self::assertSame(1, filemtime($graphFile));
    }

    public function test_existing_scoped_cache_graphs_and_ownership_records_remain_compatible(): void
    {
        $source = $this->cacheDir . '/a.phel';
        file_put_contents($source, '(ns app.a)');
        $legacy = new ScopedCache(new FileCache($this->cacheDir . '/dependency-graph'));
        $legacy->put('file:' . $source, ['source' => $source, 'namespace' => 'app.a']);
        $legacy->put('ns:app.a', 'app.a');
        $legacy->dependsOn('file:' . $source, 'ns:app.a');

        $compiled = new CompiledCodeCache($this->cacheDir, 'test');
        $compiled->put($source, 'app.a', 'hash', '$x = 1;');

        $tracker = $this->tracker();
        self::assertContains($source, $tracker->invalidateDependentsOf('app.a', $compiled));
        $dependent = $this->cacheDir . '/b.phel';
        $tracker->registerDependencies($dependent, 'app.b', ['app.a']);
        $tracker->save();

        $reopened = new ScopedCache(new FileCache($this->cacheDir . '/dependency-graph'));
        self::assertContains('file:' . $dependent, $reopened->dependents('ns:app.a'));
        self::assertSame(['source' => $dependent, 'namespace' => 'app.b'], $reopened->get('file:' . $dependent));
        $compiled->save();
    }

    public function test_invalidate_dependents_cascades_to_direct_dependents(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        // file-a.phel defines ns "app\a" and depends on "phel\core"
        // file-b.phel defines ns "app\b" and depends on "app\a"
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileB, '(ns app\\b)');

        $compiledCache->put($fileA, 'app\\a', md5('(ns app\\a)'), '$x = 1;');
        $compiledCache->put($fileB, 'app\\b', md5('(ns app\\b)'), '$y = 2;');

        $tracker->registerDependencies($fileA, 'app\\a', ['phel.core']);
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        // Verify both are cached
        self::assertNotNull($compiledCache->get($fileA, md5('(ns app\\a)')));
        self::assertNotNull($compiledCache->get($fileB, md5('(ns app\\b)')));

        // Invalidate app\a — file-b should be invalidated as a dependent
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);

        self::assertContains($fileB, $invalidated);
        self::assertNull($compiledCache->get($fileB, md5('(ns app\\b)')));
    }

    public function test_persisted_graph_invalidates_transitive_dependents(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');
        foreach (['c' => [], 'b' => ['app.c'], 'a' => ['app.b'], 'other' => []] as $name => $dependencies) {
            $file = $this->cacheDir . '/' . $name . '.phel';
            file_put_contents($file, '(ns app.' . $name . ')');
            $compiledCache->put($file, 'app.' . $name, $name, '$x = 1;');
            $tracker->registerDependencies($file, 'app.' . $name, $dependencies);
        }

        $tracker->save();
        $reopened = $this->tracker();
        $invalidated = $reopened->invalidateDependentsOf('app.c', $compiledCache);
        self::assertSame([
            $this->cacheDir . '/a.phel',
            $this->cacheDir . '/b.phel',
            $this->cacheDir . '/c.phel',
        ], $this->sorted($invalidated));
        self::assertFalse($compiledCache->has($this->cacheDir . '/a.phel'));
        self::assertTrue($compiledCache->has($this->cacheDir . '/other.phel'));
    }

    public function test_invalidation_does_not_affect_unrelated_files(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $fileA = $this->cacheDir . '/a.phel';
        $fileC = $this->cacheDir . '/c.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileC, '(ns app\\c)');

        $compiledCache->put($fileA, 'app\\a', md5('(ns app\\a)'), '$x = 1;');
        $compiledCache->put($fileC, 'app\\c', md5('(ns app\\c)'), '$z = 3;');

        $tracker->registerDependencies($fileA, 'app\\a', ['phel.core']);
        $tracker->registerDependencies($fileC, 'app\\c', ['phel.core']);

        // Invalidate app\a — file-c should NOT be invalidated (no dependency on app\a)
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);

        self::assertNotContains($fileC, $invalidated);
        self::assertNotNull($compiledCache->get($fileC, md5('(ns app\\c)')));
    }

    public function test_invalidation_of_unknown_namespace_returns_empty(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $invalidated = $tracker->invalidateDependentsOf('nonexistent\\ns', $compiledCache);

        self::assertSame([], $invalidated);
    }

    public function test_register_dependencies_handles_circular_deps_gracefully(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileB, '(ns app\\b)');

        $compiledCache->put($fileA, 'app\\a', md5('(ns app\\a)'), '$x = 1;');
        $compiledCache->put($fileB, 'app\\b', md5('(ns app\\b)'), '$y = 2;');

        // Register A -> B and B -> A (circular at the namespace level)
        $tracker->registerDependencies($fileA, 'app\\a', ['app\\b']);
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        // Files point at namespaces and namespaces have no outgoing edges, so a
        // mutual require is not a cycle in the tracked graph: resolution still
        // terminates and reaches both files from either end of the cycle.
        self::assertSame(
            [$fileA, $fileB],
            $this->sorted($tracker->invalidateDependentsOf('app\\a', $compiledCache)),
        );
        self::assertSame(
            [$fileA, $fileB],
            $this->sorted($tracker->invalidateDependentsOf('app\\b', $compiledCache)),
        );

        self::assertNull($compiledCache->get($fileA, md5('(ns app\\a)')));
        self::assertNull($compiledCache->get($fileB, md5('(ns app\\b)')));
    }

    public function test_clear_removes_all_tracked_dependencies(): void
    {
        $tracker = $this->tracker();
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileB, '(ns app\\b)');

        $compiledCache->put($fileB, 'app\\b', md5('(ns app\\b)'), '$y = 2;');
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        $tracker->clear();
        $tracker->save();
        self::assertFileDoesNotExist($this->cacheDir . '/dependency-graph/.gacela-scoped-cache-graph.php');

        // After clear, invalidation should find no dependents
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);
        self::assertSame([], $invalidated);
    }

    private function tracker(): DependencyTracker
    {
        $tracker = new DependencyTracker($this->cacheDir);
        $this->trackers[] = $tracker;

        return $tracker;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function sorted(array $paths): array
    {
        sort($paths);

        return $paths;
    }
}
