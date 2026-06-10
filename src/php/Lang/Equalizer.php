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

        // Symmetric numeric-tower equality across {int, BigInt}.
        // Floats are a separate category and never equal an int/BigInt
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

    /**
     * @phpstan-assert-if-true int|BigInt $value
     */
    private function isIntegralNumber(mixed $value): bool
    {
        return is_int($value) || $value instanceof BigInt;
    }

    private function integralEquals(int|BigInt $a, int|BigInt $b): bool
    {
        if ($a instanceof BigInt) {
            return $a->equals($b);
        }

        // $a is int; $b must be BigInt here (the int === int case is
        // handled by the strict-equality fast path).
        return $b instanceof BigInt && $b->equals($a);
    }
}
