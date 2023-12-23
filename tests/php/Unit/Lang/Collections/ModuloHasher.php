<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\HasherInterface;

class ModuloHasher implements HasherInterface
{
    public function __construct(
        private readonly int $modulo = 10000,
    ) {
    }

    public function hash(mixed $value): int
    {
        return $value % $this->modulo;
    }
}
