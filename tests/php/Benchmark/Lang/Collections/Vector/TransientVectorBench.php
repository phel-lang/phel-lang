<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\TransientVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;

final class TransientVectorBench
{
    private TransientVector $vector;

    private int $nextValue = 0;

    private int $updateIndex = 0;

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_append(array $params): void
    {
        $this->setUpVector($params['size']);
        $this->vector->append($this->nextValue);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_update(array $params): void
    {
        $this->setUpVector($params['size']);
        $this->vector->update($this->updateIndex, 'new-value');
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_get(array $params): void
    {
        $this->setUpVector($params['size']);
        $this->vector->get($this->updateIndex);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_count(array $params): void
    {
        $this->setUpVector($params['size']);
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

    public function setUpVector(int $size): void
    {
        $this->vector = TransientVector::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->vector->append($i);
        }

        $this->nextValue = $size;
        $this->updateIndex = intdiv($size, 2);
    }
}
