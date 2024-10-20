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
        $this->vector = new class([]) {
            public function __construct(private readonly array $data)
            {
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

    public function bench_append(): void
    {
        $this->vector->append(1);
    }

    public function bench_update(): void
    {
        $this->vector->update(0, 'new-value');
    }
}
