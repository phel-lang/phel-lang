<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections;

use Phel\Lang\HasherInterface;

final class SimpleHasher implements HasherInterface
{
    public function hash(mixed $value): int
    {
        return $value;
    }
}
