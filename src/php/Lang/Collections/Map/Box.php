<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

final class Box
{
    public function __construct(private mixed $value)
    {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
