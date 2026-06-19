<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\EqualizerInterface;

use function is_float;
use function is_nan;

final class SimpleEqualizer implements EqualizerInterface
{
    public function equals(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }

    public function equalsKey(mixed $a, mixed $b): bool
    {
        if ($this->equals($a, $b)) {
            return true;
        }

        return is_float($a) && is_float($b) && is_nan($a) && is_nan($b);
    }
}
