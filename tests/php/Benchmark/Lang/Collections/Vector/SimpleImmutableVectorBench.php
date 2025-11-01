<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use function count;

final class SimpleImmutableVectorBench
{
    private object $vector;

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
     * @return array<string, array<string, int>>
     */
    public function provideSizes(): iterable
    {
        yield 'small' => ['size' => 16];
        yield 'medium' => ['size' => 128];
        yield 'large' => ['size' => 256];
    }

    public function setUpVector(int $size): void
    {
        $seedData = range(0, $size - 1);

        $this->vector = new readonly class($seedData) {
            public function __construct(private array $data)
            {
            }

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
