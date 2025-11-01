<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentVectorBench
{
    private PersistentVector $vector;

    private int $nextValue = 0;

    private int $updateIndex = 0;

    /**
     * @return array<string, array<string, int>>
     */
    public function provideSizes(): array
    {
        return [
            'small' => ['size' => 16],
            'medium' => ['size' => 128],
            'large' => ['size' => 256],
        ];
    }

    public function setUpVector(int $size): void
    {
        $this->vector = PersistentVector::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->vector = $this->vector->append($i);
        }

        $this->nextValue = $size;
        $this->updateIndex = intdiv($size, 2);
    }

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
}
