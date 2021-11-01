<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

/**
 * @BeforeMethods("setUp")
 */
final class SimpleImmutableVectorBench
{
    private object $vector;

    public function setUp(): void
    {
        $this->vector = new class ([]) {
            private array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function append($value): self
            {
                return new self([...$this->data, $value]);
            }

            public function update($value): self
            {
                $data = $this->data;
                $data[0] = $value;
                return new self($data);
            }
        };
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
