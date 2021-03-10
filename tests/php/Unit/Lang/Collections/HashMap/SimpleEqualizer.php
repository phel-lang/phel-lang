<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashMap;

use Phel\Lang\EqualizerInterface;

class SimpleEqualizer implements EqualizerInterface
{
    public function equals($a, $b): bool
    {
        return $a === $b;
    }
}
