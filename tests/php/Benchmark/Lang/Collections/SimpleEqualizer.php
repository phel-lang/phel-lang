<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections;

use Phel\Lang\EqualizerInterface;

final class SimpleEqualizer implements EqualizerInterface
{
    public function equals(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }
}
