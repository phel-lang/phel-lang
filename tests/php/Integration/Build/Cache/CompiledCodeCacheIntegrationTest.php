<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use Phel;
use Phel\Build\Application\FileEvaluator;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Compiler\CompilerFacade;
use Phel\Lang\LoadClasspath;
use Phel\Shared\NamespaceInformation;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Integration tests for CompiledCodeCache verifying end-to-end cache behavior.
 */
final class CompiledCodeCacheIntegrationTest extends TestCase
{
    use RemoveDirTrait;

    private string $cacheDir;

    private string $sourceFile;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-cache-integration-' . uniqid();
        $this->sourceFile = $this->cacheDir . '/source.phel';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_hit_loads_compiled_code(): void
    {
        $namespace = 'test\\integration\\cached';
        $sourceHash = md5('source content');
        $phpCode = '$testVar = "cached value";';

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, $namespace, $sourceHash, $phpCode);
        $cache->save();

        $freshCache = new CompiledCodeCache($this->cacheDir);
        $cachedPath = $freshCache->get($this->sourceFile, $sourceHash);

        self::assertNotNull($cachedPath);
        self::assertFileExists($cachedPath);

        require $cachedPath;

        /** @var string $testVar */
        self::assertSame('cached value', $testVar);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cached_sibling_load_survives_removing_a_directory_alias(): void
    {
        $root = dirname(__DIR__, 5);
        Phel::bootstrap($root);
        $compiler = new CompilerFacade();
        $compiler->initializeNewGlobalEnvironment();
        LoadClasspath::publish([$root . '/src/phel']);
        new BuildFacade()->evalFile($root . '/src/phel/core.phel');
        LoadClasspath::publish([]);

        $sourceDir = $this->cacheDir . '/real';
        mkdir($sourceDir, 0o700, true);
        $main = $sourceDir . '/main.phel';
        file_put_contents($main, "(ns cache-alias.main)\n(load \"child\")\n");
        file_put_contents($sourceDir . '/child.phel', '(in-ns cache-alias.main) (def answer 42)');
        $alias = $this->cacheDir . '/alias';
        if (!@symlink($sourceDir, $alias)) {
            self::markTestSkipped('Directory symlinks are unavailable.');
        }

        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespaceFromFile')->willReturn(new NamespaceInformation($main, 'cache-alias.main', []));
        $cache = new CompiledCodeCache($this->cacheDir . '/cache');
        $evaluator = new FileEvaluator($compiler, $extractor, $cache);

        try {
            $cold = $evaluator->evalFile($alias . '/main.phel');
            touch($cold->getTargetFile(), 1);
            $this->removeDirectoryLink($alias);

            $warm = $evaluator->evalFile($main);

            self::assertSame($cold->getTargetFile(), $warm->getTargetFile());
            clearstatcache();
            self::assertSame(1, filemtime($warm->getTargetFile()));
            self::assertSame(42, Phel::getDefinition('cache_alias.main', 'answer'));
            self::assertSame(realpath($main), new SourceMapExtractor()->extractFromFile($warm->getTargetFile())->filename());
        } finally {
            $this->removeDirectoryLink($alias);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_secondary_cache_miss_resolves_a_reloaded_primary_declaration(): void
    {
        $root = dirname(__DIR__, 5);
        Phel::bootstrap($root);
        $compiler = new CompilerFacade();
        $compiler->initializeNewGlobalEnvironment();
        LoadClasspath::publish([$root . '/src/phel']);
        new BuildFacade()->evalFile($root . '/src/phel/core.phel');

        mkdir($this->cacheDir, 0o700, true);
        $primary = $compiler->compile('(in-ns cache-forward.main) (declare later)');
        $primaryFile = $this->cacheDir . '/primary.php';
        file_put_contents($primaryFile, "<?php\n" . $primary->getPhpCode());
        $compiler->initializeNewGlobalEnvironment();
        require $primaryFile;

        $secondary = $this->cacheDir . '/secondary.phel';
        file_put_contents($secondary, '(in-ns cache-forward.main) (def read-later (fn [] later))');
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespaceFromFile')->willReturn(new NamespaceInformation($secondary, 'cache-forward.main', []));
        $evaluator = new FileEvaluator($compiler, $extractor, new CompiledCodeCache($this->cacheDir . '/cache'));

        $compiled = $evaluator->evalFile($secondary);

        self::assertFileExists($compiled->getTargetFile());
        self::assertNull(Phel::getDefinition('cache_forward.main', 'read-later')());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_miss_returns_null(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        self::assertNull($cache->get($this->cacheDir . '/missing.phel', 'somehash'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_stale_cache_returns_null(): void
    {
        $namespace = 'test\\integration\\stale';
        $oldHash = md5('old source');
        $newHash = md5('new source');

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, $namespace, $oldHash, '$x = 1;');

        self::assertNull($cache->get($this->sourceFile, $newHash));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_persists_across_instances(): void
    {
        $namespace = 'test\\integration\\persist';
        $sourceHash = md5('persistent source');
        $phpCode = '// persistent code';

        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->put($this->sourceFile, $namespace, $sourceHash, $phpCode);
        $cache1->save();

        $cache2 = new CompiledCodeCache($this->cacheDir);
        $result = $cache2->get($this->sourceFile, $sourceHash);

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_clear_removes_all(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->cacheDir . '/one.phel', 'ns\\one', 'hash1', '// code 1');
        $cache->put($this->cacheDir . '/two.phel', 'ns\\two', 'hash2', '// code 2');
        $cache->put($this->cacheDir . '/three.phel', 'ns\\three', 'hash3', '// code 3');

        $cache->clear();

        $freshCache = new CompiledCodeCache($this->cacheDir);
        self::assertNull($freshCache->get($this->cacheDir . '/one.phel', 'hash1'));
        self::assertNull($freshCache->get($this->cacheDir . '/two.phel', 'hash2'));
        self::assertNull($freshCache->get($this->cacheDir . '/three.phel', 'hash3'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_compiled_file_has_php_header(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\header', 'hash', '$x = 42;');

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\header');
        $content = file_get_contents($compiledPath);

        self::assertStringStartsWith('<?php', $content);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_multiple_files_in_one_namespace_stored_independently(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';

        $cache->put($fileA, 'shared\\ns', 'hash_a', '$result = "a";');
        $cache->put($fileB, 'shared\\ns', 'hash_b', '$result = "b";');

        self::assertNotNull($cache->get($fileA, 'hash_a'));
        self::assertNotNull($cache->get($fileB, 'hash_b'));

        $pathA = $cache->getCompiledPath($fileA, 'shared\\ns');
        $pathB = $cache->getCompiledPath($fileB, 'shared\\ns');
        self::assertNotSame($pathA, $pathB);
    }

    public function test_editing_one_namespace_keeps_a_siblings_compiled_file(): void
    {
        // Dev-loop guarantee (phel watch / test --watch): a single-file edit
        // must recompile only that file. The unchanged sibling stays a cache
        // hit and its compiled file is not rewritten.
        $changedFile = $this->cacheDir . '/changed.phel';
        $stableFile = $this->cacheDir . '/stable.phel';

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($changedFile, 'app\\changed', md5('v1'), '$x = 1;');
        $cache->put($stableFile, 'app\\stable', md5('stable'), '$y = 2;');

        $stablePath = $cache->getCompiledPath($stableFile, 'app\\stable');
        $stableMtimeBefore = filemtime($stablePath);

        // Simulate editing only `changed.phel` (new source hash).
        clearstatcache();
        $cache->put($changedFile, 'app\\changed', md5('v2'), '$x = 2;');
        $cache->save();

        $fresh = new CompiledCodeCache($this->cacheDir);
        // Unchanged sibling: still a hit, same file, not rewritten.
        self::assertNotNull($fresh->get($stableFile, md5('stable')));
        clearstatcache();
        self::assertSame($stableMtimeBefore, filemtime($stablePath), 'sibling compiled file must not be rewritten');
        // Edited namespace: old hash misses, new hash hits.
        self::assertNull($fresh->get($changedFile, md5('v1')));
        self::assertNotNull($fresh->get($changedFile, md5('v2')));
    }

    public function test_put_preserves_entries_written_by_other_instances(): void
    {
        // Two instances each put a distinct entry and flush at shutdown
        // (here driven explicitly via save()). The second flush must read-
        // merge the first instance's on-disk entry before writing, or the
        // next run sees a cache miss and recompiles everything. This guards
        // both the (load ...) nested-instance case and concurrent
        // `phel test` workers writing the index in separate processes.
        $outerFile = $this->cacheDir . '/outer.phel';
        $nestedFile = $this->cacheDir . '/nested.phel';

        $outer = new CompiledCodeCache($this->cacheDir);
        $nested = new CompiledCodeCache($this->cacheDir);

        $nested->put($nestedFile, 'nested\\ns', 'hash_nested', '$result = "nested";');
        $nested->save();

        $outer->put($outerFile, 'outer\\ns', 'hash_outer', '$result = "outer";');
        $outer->save();

        $fresh = new CompiledCodeCache($this->cacheDir);
        self::assertNotNull($fresh->get($outerFile, 'hash_outer'));
        self::assertNotNull($fresh->get($nestedFile, 'hash_nested'));
    }

    private function removeDirectoryLink(string $path): void
    {
        if (!is_link($path)) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            rmdir($path);
            return;
        }

        unlink($path);
    }

}
