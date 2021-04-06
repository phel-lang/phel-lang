<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

/**
 * @BeforeMethods("setUp")
 */
final class CustomVectorBench
{
    private CustomVector $vector;

    public function setUp(): void
    {
        $this->vector = new CustomVector(0, [], []);
    }

    /**
     * @Iterations(10)
     * @Revs(1000)
     */
    public function bench_append(): void
    {
        $this->vector->append(1);
    }

    /**
     * @Iterations(10)
     * @Revs(1000)
     */
    public function bench_update(): void
    {
        $this->vector->update(0, 'new-value');
    }
}
