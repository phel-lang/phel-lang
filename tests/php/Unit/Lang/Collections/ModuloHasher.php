<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\HasherInterface;

class ModuloHasher implements HasherInterface
{
    private int $modulo;

    public function __construct($modulo = 10000)
    {
        $this->modulo = $modulo;
    }

    public function hash(mixed $value): int
    {
        return $value % $this->modulo;
    }
}
