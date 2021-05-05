<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\EqualizerInterface;

final class SimpleEqualizer implements EqualizerInterface
{
    public function equals($a, $b): bool
    {
        return $a === $b;
    }
}
