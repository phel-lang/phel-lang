<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `PersistentVector` operation micro-benchmarks. All ops are pure, so
 * setUp runs once per iteration via `@BeforeMethods` and 1000
 * revolutions measure just the operation.
 *
 * Uses the real `Hasher` / `Equalizer` so regressions in either show
 * up here. Sizes include 1024 to cross the depth-2 tree boundary.
 */
final class PersistentVectorBench
{
    private PersistentVector $vector;

    private int $nextValue = 0;

    private int $updateIndex = 0;

    /**
     * @BeforeMethods("setUpVector")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_append(): void
    {
        $this->vector->append($this->nextValue);
    }

    /**
     * @BeforeMethods("setUpVector")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_update(): void
    {
        $this->vector->update($this->updateIndex, 'new-value');
    }

    /**
     * @BeforeMethods("setUpVector")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_get(): void
    {
        $this->vector->get($this->updateIndex);
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

    public function setUpVector(array $params): void
    {
        $size = $params['size'];

        $this->vector = PersistentVector::empty(new Hasher(), new Equalizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->vector = $this->vector->append($i);
        }

        $this->nextValue = $size;
        $this->updateIndex = intdiv($size, 2);
    }
}
