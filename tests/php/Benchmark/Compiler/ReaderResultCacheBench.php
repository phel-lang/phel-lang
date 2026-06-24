<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Cache\CachedReaderResult;
use Phel\Compiler\Infrastructure\Cache\FileSystemReaderResultCache;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Isolates the front half of the compiler (lex -> parse -> read) over the whole
 * `phel.core` source, contrasting a cold run that re-lexes/parses/reads every
 * form against a warm run served from the {@see FileSystemReaderResultCache}
 * (gzip-inflate + unserialize). This is the slice the intermediate-artifact
 * cache replaces on a warm rebuild; analysis + emission are unchanged by it and
 * are excluded here on purpose.
 *
 * @BeforeMethods("setUp")
 */
final class ReaderResultCacheBench
{
    private CompilerFacade $facade;

    private string $source = '';

    private string $cacheDir = '';

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';
        Phel::bootstrap($projectRoot);
        GlobalEnvironmentSingleton::initializeNew();

        $this->facade = new CompilerFacade();
        $this->source = (string) file_get_contents($projectRoot . 'src/phel/core.phel');
        $this->cacheDir = sys_get_temp_dir() . '/phel-rr-cache-bench-' . uniqid();

        // Prime the persisted cache once so the warm subject reads it back.
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'bench');
        $cache->save($this->source, 0, $this->readFrontHalf());
    }

    /**
     * Cold: full lex + parse + read of every top-level form.
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_cold_front_half(): void
    {
        $this->readFrontHalf();
    }

    /**
     * Warm: the persisted reader results are inflated and unserialized instead
     * of re-deriving them.
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_warm_front_half(): void
    {
        new FileSystemReaderResultCache($this->cacheDir, 'bench')->load($this->source, 0);
    }

    /**
     * @return list<CachedReaderResult>
     */
    private function readFrontHalf(): array
    {
        $tokenStream = $this->facade->lexString($this->source, 'bench');

        $entries = [];
        while (true) {
            $parseTree = $this->facade->parseNext($tokenStream);
            if ($parseTree === null) {
                break;
            }

            if ($parseTree instanceof TriviaNodeInterface) {
                continue;
            }

            $genBefore = Symbol::genCounter();
            $readerResult = $this->facade->read($parseTree);
            $entries[] = new CachedReaderResult($readerResult, Symbol::genCounter() - $genBefore);
        }

        return $entries;
    }
}
