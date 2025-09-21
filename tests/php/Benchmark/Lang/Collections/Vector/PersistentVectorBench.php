<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentVectorBench
{
    private const int SEED_SIZE = 128;

    private PersistentVector $vector;

    private int $nextValue = 0;

    private int $updateIndex = 0;

    public function setUp(): void
    {
        $this->vector = PersistentVector::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < self::SEED_SIZE; ++$i) {
            $this->vector = $this->vector->append($i);
        }

        $this->nextValue = self::SEED_SIZE;
        $this->updateIndex = intdiv(self::SEED_SIZE, 2);
    }

    public function bench_append(): void
    {
        $this->vector->append($this->nextValue);
        ++$this->nextValue;
    }

    public function bench_update(): void
    {
        $this->vector->update($this->updateIndex, 'new-value');
    }
}
