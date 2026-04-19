<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

final class Box
{
    public function __construct(private ?bool $value) {}

    public function getValue(): ?bool
    {
        return $this->value;
    }

    public function setValue(bool $value): void
    {
        $this->value = $value;
    }
}
