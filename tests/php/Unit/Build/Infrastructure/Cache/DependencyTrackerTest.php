<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\Cache\DependencyTracker;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class DependencyTrackerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-dep-tracker-test-' . uniqid();
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    public function test_invalidate_dependents_cascades_to_direct_dependents(): void
    {
        $tracker = new DependencyTracker($this->cacheDir);
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        // file-a.phel defines ns "app\a" and depends on "phel\core"
        // file-b.phel defines ns "app\b" and depends on "app\a"
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileB, '(ns app\\b)');

        $compiledCache->put($fileA, 'app\\a', md5('(ns app\\a)'), '$x = 1;');
        $compiledCache->put($fileB, 'app\\b', md5('(ns app\\b)'), '$y = 2;');

        $tracker->registerDependencies($fileA, 'app\\a', ['phel\\core']);
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        // Verify both are cached
        self::assertNotNull($compiledCache->get($fileA, md5('(ns app\\a)')));
        self::assertNotNull($compiledCache->get($fileB, md5('(ns app\\b)')));

        // Invalidate app\a — file-b should be invalidated as a dependent
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);

        self::assertContains($fileB, $invalidated);
        self::assertNull($compiledCache->get($fileB, md5('(ns app\\b)')));
    }

    public function test_invalidation_does_not_affect_unrelated_files(): void
    {
        $tracker = new DependencyTracker($this->cacheDir);
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $fileA = $this->cacheDir . '/a.phel';
        $fileC = $this->cacheDir . '/c.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileC, '(ns app\\c)');

        $compiledCache->put($fileA, 'app\\a', md5('(ns app\\a)'), '$x = 1;');
        $compiledCache->put($fileC, 'app\\c', md5('(ns app\\c)'), '$z = 3;');

        $tracker->registerDependencies($fileA, 'app\\a', ['phel\\core']);
        $tracker->registerDependencies($fileC, 'app\\c', ['phel\\core']);

        // Invalidate app\a — file-c should NOT be invalidated (no dependency on app\a)
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);

        self::assertNotContains($fileC, $invalidated);
        self::assertNotNull($compiledCache->get($fileC, md5('(ns app\\c)')));
    }

    public function test_invalidation_of_unknown_namespace_returns_empty(): void
    {
        $tracker = new DependencyTracker($this->cacheDir);
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $invalidated = $tracker->invalidateDependentsOf('nonexistent\\ns', $compiledCache);

        self::assertSame([], $invalidated);
    }

    public function test_register_dependencies_handles_circular_deps_gracefully(): void
    {
        $tracker = new DependencyTracker($this->cacheDir);

        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileA, '(ns app\\a)');
        file_put_contents($fileB, '(ns app\\b)');

        // Register A -> B and B -> A (circular)
        $tracker->registerDependencies($fileA, 'app\\a', ['app\\b']);
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        // Should not throw — cycles are silently ignored
        self::assertTrue(true);
    }

    public function test_clear_removes_all_tracked_dependencies(): void
    {
        $tracker = new DependencyTracker($this->cacheDir);
        $compiledCache = new CompiledCodeCache($this->cacheDir, 'test');

        $fileB = $this->cacheDir . '/b.phel';
        file_put_contents($fileB, '(ns app\\b)');

        $compiledCache->put($fileB, 'app\\b', md5('(ns app\\b)'), '$y = 2;');
        $tracker->registerDependencies($fileB, 'app\\b', ['app\\a']);

        $tracker->clear();

        // After clear, invalidation should find no dependents
        $invalidated = $tracker->invalidateDependentsOf('app\\a', $compiledCache);
        self::assertSame([], $invalidated);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }
}
