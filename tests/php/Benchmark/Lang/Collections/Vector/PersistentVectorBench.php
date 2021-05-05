<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentVectorBench
{
    private PersistentVector $vector;

    public function setUp(): void
    {
        $this->vector = PersistentVector::empty(new SimpleHasher(), new SimpleEqualizer());
    }

    /**
     * @Iterations(5)
     * @Revs(1000)
     */
    public function bench_append(): void
    {
        $this->vector->append(1);
    }

    /**
     * @Iterations(5)
     * @Revs(1000)
     */
    public function bench_update(): void
    {
        $this->vector->update(0, 'new-value');
    }
}
