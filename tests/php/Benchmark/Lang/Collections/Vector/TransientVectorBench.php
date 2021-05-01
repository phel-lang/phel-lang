<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\TransientVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;

/**
 * @BeforeMethods("setUp")
 */
final class TransientVectorBench
{
    private TransientVector $vector;

    public function setUp(): void
    {
        $this->vector = TransientVector::empty(new SimpleHasher(), new SimpleEqualizer());
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
