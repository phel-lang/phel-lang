<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function count;

/**
 * Naive array-copy immutable vector, kept as a baseline to compare
 * against `PersistentVector`. The cost of `append` here is O(N) copy;
 * PersistentVector should outperform it at non-trivial sizes.
 */
final class SimpleImmutableVectorBench
{
    private object $vector;

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
     * @return array<string, array<string, int>>
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
        $seedData = range(0, $size - 1);

        $this->vector = new readonly class($seedData) {
            public function __construct(private array $data) {}

            public function append($value): self
            {
                return new self([...$this->data, $value]);
            }

            public function update(int $index, $value): self
            {
                $data = $this->data;
                $data[$index] = $value;

                return new self($data);
            }

            public function get(int $index)
            {
                return $this->data[$index] ?? null;
            }

            public function count(): int
            {
                return count($this->data);
            }
        };

        $this->nextValue = $size;
        $this->updateIndex = intdiv($size, 2);
    }
}
