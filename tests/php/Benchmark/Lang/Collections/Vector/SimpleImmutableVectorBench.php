<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;

/**
 * @BeforeMethods("setUp")
 */
final class SimpleImmutableVectorBench
{
    private const int SEED_SIZE = 128;

    private object $vector;

    private int $nextValue = 0;

    private int $updateIndex = 0;

    public function setUp(): void
    {
        $seedData = range(0, self::SEED_SIZE - 1);

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
        };

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
