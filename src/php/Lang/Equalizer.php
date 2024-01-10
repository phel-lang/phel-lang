<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Equalizer implements EqualizerInterface
{
    /**
     * @param mixed $a Left value
     * @param mixed $b Right value
     *
     * @return bool True, if $a is equals $b
     */
    public function equals(mixed $a, mixed $b): bool
    {
        if ($a instanceof EqualsInterface) {
            return $a->equals($b);
        }

        return $a === $b;
    }
}
