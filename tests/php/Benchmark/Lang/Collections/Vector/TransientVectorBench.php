<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\TransientVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `TransientVector` operation micro-benchmarks.
 *
 * Transients are mutable: `append` / `update` modify `$this->vector`
 * in place and then return it. With `@Revs(1000)` that means:
 *   - `bench_append` measures the amortised per-append cost of 1000
 *     sequential appends starting from the initial size — the
 *     realistic batch-insert case transients are designed for.
 *   - `bench_update` writes 1000× to a fixed slot — length stays
 *     constant, so the measurement is stable across revolutions.
 *   - `bench_count` / `bench_get` are read-only — no caveat.
 *
 * `@BeforeMethods` resets the vector at the start of each iteration,
 * so the 10 reported iterations are independent samples.
 */
final class TransientVectorBench
{
    private TransientVector $vector;

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
     * @BeforeMethods("setUpVector")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_count(): void
    {
        $this->vector->count();
    }

    /**
     * @return iterable<string, array<string, int>>
     */
    public function provideSizes(): iterable
    {
        yield 'small' => ['size' => 16];
        yield 'medium' => ['size' => 128];
        yield 'large' => ['size' => 256];
    }

    public function setUpVector(array $params): void
    {
        $size = $params['size'];

        $this->vector = TransientVector::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->vector->append($i);
        }

        $this->nextValue = $size;
        $this->updateIndex = intdiv($size, 2);
    }
}
