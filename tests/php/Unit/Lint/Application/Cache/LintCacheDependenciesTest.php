<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Cache;

use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Cache\LintCacheDependencies;
use Phel\Lint\Application\Cache\LintCacheLoadedSources;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LintCacheDependenciesTest extends TestCase
{
    use RemoveDirTrait;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phel-lint-dependencies-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->directory);
    }

    public function test_transitive_changes_invalidate_a_consumer_but_unrelated_changes_keep_its_cached_result(): void
    {
        $consumer = $this->directory . '/consumer.phel';
        $dependency = $this->directory . '/dependency.phel';
        $transitive = $this->directory . '/transitive.phel';
        $unrelated = $this->directory . '/unrelated.phel';
        foreach ([$consumer, $dependency, $transitive, $unrelated] as $file) {
            file_put_contents($file, '(ns initial)');
        }

        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('getNamespaceFromFile')->willReturn(new NamespaceInformation($consumer, 'consumer', ['dependency']));
        $run->method('getDependenciesForNamespace')->willReturn([
            new NamespaceInformation($dependency, 'dependency', ['transitive']),
            new NamespaceInformation($transitive, 'transitive', []),
        ]);
        $dependencies = new LintCacheDependencies($run, [$this->directory], new LintCacheLoadedSources($this->createStub(CompilerFacadeInterface::class)));
        $fingerprint = $dependencies->fingerprintFor($consumer);
        self::assertNotNull($fingerprint);
        $cache = new LintCache($this->directory . '/cache', 'rules');
        $cache->put($consumer, [], $fingerprint);

        file_put_contents($unrelated, '(ns changed)');
        self::assertSame($fingerprint, $dependencies->fingerprintFor($consumer));
        self::assertSame([], $cache->get($consumer, $fingerprint));

        file_put_contents($transitive, '(ns changed)');
        $changed = $dependencies->fingerprintFor($consumer);
        self::assertNotNull($changed);
        self::assertNotSame($fingerprint, $changed);
        self::assertNull($cache->get($consumer, $changed));

        unlink($transitive);
        self::assertNull($dependencies->fingerprintFor($consumer));
    }

    public function test_failed_namespace_resolution_prevents_cache_reuse(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('getNamespaceFromFile')->willThrowException(new RuntimeException('invalid namespace'));

        self::assertNull(new LintCacheDependencies($run, [], new LintCacheLoadedSources($this->createStub(CompilerFacadeInterface::class)))->fingerprintFor('invalid.phel'));
    }
}
