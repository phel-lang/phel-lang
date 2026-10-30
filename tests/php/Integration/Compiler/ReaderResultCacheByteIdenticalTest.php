<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Compiler\Application\CodeCompiler;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Cache\CachedReaderResult;
use Phel\Compiler\Domain\Cache\ReaderResultCacheInterface;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Infrastructure\Cache\FileSystemReaderResultCache;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use Phel\Shared\VersionFinder;
use PhelTest\Support\Subprocess;

use function dirname;

/**
 * The reader-result cache must be transparent: replaying cached forms through
 * analysis + emission has to produce byte-identical PHP to a cold compile.
 *
 * The interesting case is gensym. The source below exercises both reader
 * gensym (`v#` quasiquote auto-gensym, `|(...)` short-fn) and analyzer gensym
 * (`let` shadows, macro expansion). A naive cache that skipped the read phase
 * would shift the analyzer/emitter gensym numbering and diverge; the recorded
 * per-form read-gensym delta is what keeps the two outputs identical.
 */
final class ReaderResultCacheByteIdenticalTest extends AbstractCompilerRuntimeTestCase
{
    // Deliberately def-free so recompiling in the same process is idempotent
    // (a real warm rebuild is a fresh process). Still exercises reader gensym
    // (`v#` quasiquote auto-gensym, `|(...)` short-fn) and analyzer gensym
    // (`let`/`fn` shadows + macro expansion).
    private const string SOURCE = <<<'PHEL'
        (let [base (inc 4)] `(pair v# ~base))
        ((fn [x] (let [y (* x x)] (+ y y))) 6)
        (map #(+ % 1) [1 2 3])
        PHEL;

    private string $diskCacheDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskCacheDir = sys_get_temp_dir() . '/phel-rr-cache-itest-' . uniqid('', true);
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();

        $dir = $this->diskCacheDir . '/read-result';
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
        @rmdir($this->diskCacheDir);
    }

    public function test_warm_cache_hit_is_byte_identical_to_cold_compile(): void
    {
        $cache = $this->inMemoryCache();
        $compiler = $this->compilerWithCache($cache);
        $options = new CompileOptions()->setSource('cache-byte-test')->setIsEnabledSourceMaps(false);

        // Cold: miss, runs the full pipeline and populates the cache.
        Symbol::resetGen();
        $cold = $compiler->compileString(self::SOURCE, $options)->getCodeWithSourceMap();

        self::assertSame(1, $cache->saveCount, 'cold compile should populate the cache');
        self::assertSame(0, $cache->hitCount);

        // Warm: hit, skips lex/parse/read and replays from the cache.
        Symbol::resetGen();
        $warm = $compiler->compileString(self::SOURCE, $options)->getCodeWithSourceMap();

        self::assertSame(1, $cache->hitCount, 'second compile should hit the cache');
        self::assertSame($cold, $warm, 'warm cache hit must emit byte-identical PHP');
    }

    public function test_disk_backed_cache_round_trip_is_byte_identical(): void
    {
        // End-to-end through the real FileSystemReaderResultCache: cold compile
        // serializes + gzips the read results to disk, warm compile inflates +
        // unserializes them and replays. Exercises the full on-disk round-trip.
        $cache = new FileSystemReaderResultCache($this->diskCacheDir, 'itest');
        $compiler = $this->compilerWithCache($cache);
        $options = new CompileOptions()->setSource('cache-disk-test')->setIsEnabledSourceMaps(false);

        Symbol::resetGen();
        $cold = $compiler->compileString(self::SOURCE, $options)->getCodeWithSourceMap();

        self::assertNotEmpty(
            glob($this->diskCacheDir . '/read-result/*.cache') ?: [],
            'cold compile should write a cache file to disk',
        );

        Symbol::resetGen();
        $warm = $compiler->compileString(self::SOURCE, $options)->getCodeWithSourceMap();

        self::assertSame($cold, $warm, 'disk-backed warm hit must emit byte-identical PHP');
        self::assertStringContainsString('Symbol::createGenerated("v__', $warm, 'v# must stay a generated symbol');
    }

    public function test_old_float_key_tries_are_rebuilt_before_macro_expansion(): void
    {
        $source = '(println (reader-cache-float-value {0 0 1 1 2 2 3 3 4 4 5 5 6 6 7 7 8 8 9 9 1.5 42}))';
        $version = VersionFinder::LATEST_VERSION;
        $legacyPath = $this->diskCacheDir . '/read-result/' . md5('2|' . $version . '|O0|' . $source) . '.cache';
        $legacyWriter = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$hasher = file_get_contents($argv[1] . '/src/php/Lang/Hasher.php');
$hasher = str_replace("return crc32(pack('E', \$value));", 'return (int) $value;', $hasher, $replacements);
if ($replacements !== 1 || class_exists(\Phel\Lang\Hasher::class, false)) {
    throw new RuntimeException('Cannot prepare the legacy float hasher');
}
eval(substr($hasher, 5));
$factory = new \Phel\Compiler\CompilerFactory();
$tokens = $factory->createLexer()->lexString($argv[3]);
$tree = $factory->createParser()->parseNext($tokens);
$result = $factory->createReader()->read($tree);
$map = $result->getAst()->get(1)->get(1);
if (!$map instanceof \Phel\Lang\Collections\Map\PersistentHashMap || count($map) !== 11 || $map->find(1.5) !== 42) {
    throw new RuntimeException('The legacy fixture must contain a working float-key trie');
}
mkdir(dirname($argv[2]), 0755, true);
file_put_contents($argv[2], gzcompress(serialize([new \Phel\Compiler\Domain\Cache\CachedReaderResult($result, 0)])));
PHP;
        $written = Subprocess::run([PHP_BINARY, '-r', $legacyWriter, dirname(__DIR__, 4), $legacyPath, $source]);
        self::assertSame(0, $written->exitCode, $written->stderr);
        $legacy = unserialize(gzuncompress(file_get_contents($legacyPath)));
        $map = $legacy[0]->readerResult->getAst()->get(1)->get(1);
        self::assertInstanceOf(PersistentHashMap::class, $map);
        self::assertNull($map->find(1.5), 'The old trie is incompatible with the new float hasher');

        $this->compilerFacade->eval('(defmacro reader-cache-float-value [m] (or (get m 1.5) (throw (RuntimeException. "Cached float key was lost"))))');
        $cache = new FileSystemReaderResultCache($this->diskCacheDir, $version);
        $options = new CompileOptions()->setSource('float-reader-cache')->setIsEnabledSourceMaps(false);
        Symbol::resetGen();
        $cold = $this->compilerWithCache($cache)->compileString($source, $options)->getCodeWithSourceMap();
        self::assertStringContainsString('42', $cold);
        self::assertSame(42, $cache->load($source, 0)[0]->readerResult->getAst()->get(1)->get(1)->find(1.5));

        $freshFiles = array_values(array_diff(glob($this->diskCacheDir . '/read-result/*.cache'), [$legacyPath]));
        self::assertCount(1, $freshFiles, 'Rebuilding must write the new format without reusing the old blob');
        touch($freshFiles[0], 1);
        Symbol::resetGen();
        $warm = $this->compilerWithCache(new FileSystemReaderResultCache($this->diskCacheDir, $version))
            ->compileString($source, $options)->getCodeWithSourceMap();
        self::assertSame($cold, $warm);
        clearstatcache();
        self::assertSame(1, filemtime($freshFiles[0]), 'Warm macro expansion must read the new cache without rewriting it');
    }

    public function test_source_consumes_reader_phase_gensym(): void
    {
        // Sanity guard: the source genuinely consumes reader-phase gensym, so a
        // cache that did NOT replay the delta would shift later gensym numbers.
        // Here we assert at least one form recorded a non-zero read delta, which
        // is precisely the state the warm path must reproduce.
        $cache = $this->inMemoryCache();
        $compiler = $this->compilerWithCache($cache);
        $options = new CompileOptions()->setSource('cache-delta-test')->setIsEnabledSourceMaps(false);

        Symbol::resetGen();
        $compiler->compileString(self::SOURCE, $options);

        $deltas = array_map(static fn(CachedReaderResult $e): int => $e->gensymDelta, $cache->stored);
        self::assertNotEmpty($deltas);
        self::assertGreaterThan(0, array_sum($deltas), 'source should consume reader-phase gensym');
    }

    private function compilerWithCache(ReaderResultCacheInterface $cache): CodeCompilerInterface
    {
        $factory = new CompilerFactory();

        return new CodeCompiler(
            $factory->createLexer(),
            $factory->createParser(),
            $factory->createReader(),
            $factory->createAnalyzer(),
            $factory->createStatementEmitter(false),
            $factory->createFileEmitter(false),
            $factory->createEvaluator(),
            $cache,
        );
    }

    /**
     * @return object{saveCount: int, hitCount: int, stored: list<CachedReaderResult>}&ReaderResultCacheInterface
     */
    private function inMemoryCache(): ReaderResultCacheInterface
    {
        return new class() implements ReaderResultCacheInterface {
            public int $saveCount = 0;

            public int $hitCount = 0;

            /** @var list<CachedReaderResult> */
            public array $stored = [];

            public function load(string $phelCode, int $optimizationLevel): ?array
            {
                if ($this->stored === []) {
                    return null;
                }

                ++$this->hitCount;

                return $this->stored;
            }

            public function save(string $phelCode, int $optimizationLevel, array $entries): void
            {
                ++$this->saveCount;
                $this->stored = $entries;
            }
        };
    }
}
