<?php

declare(strict_types=1);

namespace Phel\Lang;

use Gacela\Container\Attribute\Singleton;

use function is_int;

#[Singleton]
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
        if ($a === $b) {
            return true;
        }

        // Symmetric numeric-tower equality across {int, BigInteger}.
        // Floats are a separate category and never equal an int/BigInteger
        // under `=` (use `==` for cross-category comparison).
        if ($this->isIntegralNumber($a) && $this->isIntegralNumber($b)) {
            return $this->integralEquals($a, $b);
        }

        if ($a instanceof EqualsInterface) {
            return $a->equals($b);
        }

        // Dispatch on $b when only the right side carries equality semantics.
        if ($b instanceof EqualsInterface) {
            return $b->equals($a);
        }

        return false;
    }

    private function isIntegralNumber(mixed $value): bool
    {
        return is_int($value) || $value instanceof BigInteger;
    }

    private function integralEquals(int|BigInteger $a, int|BigInteger $b): bool
    {
        if ($a instanceof BigInteger) {
            return $a->equals($b);
        }

        // $a is int; $b must be BigInteger here (the int === int case is
        // handled by the strict-equality fast path).
        return $b instanceof BigInteger && $b->equals($a);
    }
}
