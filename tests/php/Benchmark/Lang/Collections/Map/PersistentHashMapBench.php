<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `PersistentHashMap` operation micro-benchmarks.
 *
 * Every subject is pure (operations return a new map rather than
 * mutating `$this->map`), so setUp runs once per iteration via
 * `@BeforeMethods` and the hot loop measures only the operation under
 * test. 1000 revolutions per iteration amortise `microtime` overhead
 * on nanosecond-scale lookups; 10 iterations give a usable median.
 *
 * Uses the real `Hasher` / `Equalizer`, not test doubles, so a
 * regression in either propagates visibly into map throughput here.
 * Sizes span the HAMT branching boundary (32) and the depth-2
 * boundary (1024) to catch depth-transition cost changes.
 */
final class PersistentHashMapBench
{
    private PersistentHashMap $map;

    private int $nextKey = 0;

    private int $queryKey = 0;

    /**
     * @BeforeMethods("setUpMap")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_put(): void
    {
        $this->map->put($this->nextKey, $this->nextKey);
    }

    /**
     * @BeforeMethods("setUpMap")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_find(): void
    {
        $this->map->find($this->queryKey);
    }

    /**
     * @BeforeMethods("setUpMap")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_contains(): void
    {
        $this->map->contains($this->queryKey);
    }

    /**
     * @BeforeMethods("setUpMap")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_remove(): void
    {
        $this->map->remove($this->queryKey);
    }

    /**
     * @BeforeMethods("setUpMap")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_count(): void
    {
        $this->map->count();
    }

    /**
     * @return iterable<string, array<string, int>>
     */
    public function provideSizes(): iterable
    {
        yield 'small' => ['size' => 16];
        yield 'medium' => ['size' => 128];
        yield 'boundary' => ['size' => 1024];
    }

    public function setUpMap(array $params): void
    {
        $size = $params['size'];

        $this->map = PersistentHashMap::empty(new Hasher(), new Equalizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->map = $this->map->put($i, $i);
        }

        $this->nextKey = $size;
        $this->queryKey = intdiv($size, 2);
    }
}
