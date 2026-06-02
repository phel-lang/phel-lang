<?php

declare(strict_types=1);

namespace Phel\Lang;

use function abs;
use function intdiv;

/**
 * Detects whether a native PHP `int` arithmetic operation would leave the
 * signed 64-bit range, so {@see NumericOperations} can promote to
 * {@see BigInt} before the silent float coercion or wrap-around happens.
 *
 * Each predicate is pure and only inspects the operands; it performs no
 * arithmetic that could itself overflow.
 */
final class IntegerOverflow
{
    public static function onAdd(int $a, int $b): bool
    {
        if ($a > 0 && $b > 0) {
            return $a > PHP_INT_MAX - $b;
        }

        if ($a < 0 && $b < 0) {
            return $a < PHP_INT_MIN - $b;
        }

        return false;
    }

    public static function onSubtract(int $a, int $b): bool
    {
        if ($a >= 0 && $b < 0) {
            // a - b > PHP_INT_MAX when b < a - PHP_INT_MAX.
            return $a > PHP_INT_MAX + $b;
        }

        if ($a < 0 && $b > 0) {
            return $a < PHP_INT_MIN + $b;
        }

        return false;
    }

    public static function onMultiply(int $a, int $b): bool
    {
        if ($a === 0 || $b === 0 || $a === 1 || $b === 1) {
            return false;
        }

        // PHP_INT_MIN cannot be negated within int range, so any multiply of
        // PHP_INT_MIN by anything other than 0 or 1 leaves the int range.
        if ($a === PHP_INT_MIN || $b === PHP_INT_MIN) {
            return true;
        }

        // Compare against PHP_INT_MAX magnitude using truncating intdiv.
        // Both operands are now safe to negate within int range.
        return intdiv(PHP_INT_MAX, abs($a)) < abs($b);
    }
}
