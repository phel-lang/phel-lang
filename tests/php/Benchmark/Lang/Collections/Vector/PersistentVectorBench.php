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
    private const int EQUAL_SIZE = 1024;

    /**
     * Largest element count whose `31 * hash + element` accumulator stays
     * within PHP's signed-int range. Beyond this the running hash promotes
     * to float and `AbstractPersistentVector::hash` (typed `?int` cache)
     * would fatal, so the hash bench stops short of it. This is the
     * effective ceiling of the production hash path, not an arbitrary
     * choice.
     */
    private const int HASH_SIZE = 12;

    private PersistentVector $vector;

    /**
     * A structurally-equal but distinct vector, so `equals` walks the
     * whole length instead of short-circuiting on identity.
     */
    private PersistentVector $equalVector;

    private Hasher $hasher;

    private Equalizer $equalizer;

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
     * Element-wise equality of two large, distinct-but-equal vectors.
     * Identity short-circuits are avoided (the vectors are separate
     * instances), so the full O(n) `Equalizer` walk is measured.
     *
     * @BeforeMethods("setUpEqualVectors")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_equals(): void
    {
        $this->vector->equals($this->equalVector);
    }

    /**
     * Full-length hash walk. `AbstractPersistentVector::hash` memoises its
     * result, so a fresh vector is built per revolution to keep the O(n)
     * `Hasher` walk in the measurement rather than returning a cached
     * value. Build cost is shared overhead common to any change, so
     * regressions in `Hasher` still surface here. The element count is
     * capped at `HASH_SIZE` (see that constant) because the production
     * `31 * hash` accumulator overflows beyond it.
     *
     * @BeforeMethods("setUpHasher")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_hash(): void
    {
        $vector = PersistentVector::empty($this->hasher, $this->equalizer);
        for ($i = 0; $i < self::HASH_SIZE; ++$i) {
            $vector = $vector->append($i);
        }

        $vector->hash();
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

    public function setUpEqualVectors(): void
    {
        $this->vector = PersistentVector::empty(new Hasher(), new Equalizer());
        $this->equalVector = PersistentVector::empty(new Hasher(), new Equalizer());

        for ($i = 0; $i < self::EQUAL_SIZE; ++$i) {
            $this->vector = $this->vector->append($i);
            $this->equalVector = $this->equalVector->append($i);
        }
    }

    public function setUpHasher(): void
    {
        $this->hasher = new Hasher();
        $this->equalizer = new Equalizer();
    }
}
