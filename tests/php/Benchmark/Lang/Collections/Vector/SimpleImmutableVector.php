<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

class SimpleImmutableVector
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function append($value): SimpleImmutableVector
    {
        return new SimpleImmutableVector([...$this->data, $value]);
    }
}
