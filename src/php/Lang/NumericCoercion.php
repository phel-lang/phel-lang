<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;

use function is_float;
use function is_int;
use function sprintf;

/**
 * Numeric type lifting and validation shared by {@see NumericOperations}.
 *
 * The dispatch ladders need to coerce a mixed operand up to a common type
 * (`float`, {@see BigInt}, {@see BigDecimal}, or a rational operand) and to
 * collapse an exact {@see BigInt} result back to a native `int` when it fits.
 * Those rules live here so the dispatch table stays focused on contagion
 * order rather than per-type conversion plumbing.
 */
final class NumericCoercion
{
    /**
     * @phpstan-assert int|float|BigInt|Ratio|BigDecimal $value
     *
     * @psalm-assert int|float|BigInt|Ratio|BigDecimal $value
     */
    public static function ensureNumeric(mixed $value): void
    {
        if (is_int($value) || is_float($value)) {
            return;
        }

        if ($value instanceof BigInt || $value instanceof Ratio || $value instanceof BigDecimal) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf('Expected a number, got %s', get_debug_type($value)),
        );
    }

    public static function toBigDecimal(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if ($value instanceof BigInt) {
            return BigDecimal::fromBigInt($value);
        }

        if (is_int($value)) {
            return BigDecimal::fromInt($value);
        }

        if (is_float($value)) {
            return BigDecimal::fromFloat($value);
        }

        if ($value instanceof Ratio) {
            return BigDecimal::fromBigInt($value->numerator())
                ->divideExact(BigDecimal::fromBigInt($value->denominator()));
        }

        throw new InvalidArgumentException(
            sprintf('Cannot lift %s to BigDecimal', get_debug_type($value)),
        );
    }

    public static function toFloat(mixed $value): float
    {
        if ($value instanceof Ratio) {
            return $value->toFloat();
        }

        if ($value instanceof BigInt || $value instanceof BigDecimal) {
            return (float) (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot lift %s to float', get_debug_type($value)),
        );
    }

    public static function toBigInt(mixed $value): BigInt
    {
        if ($value instanceof BigInt) {
            return $value;
        }

        if (is_int($value)) {
            return BigInt::fromInt($value);
        }

        throw new InvalidArgumentException(
            sprintf('Cannot lift %s to BigInt', get_debug_type($value)),
        );
    }

    public static function rationalOperand(mixed $value): Ratio|BigInt|int
    {
        if ($value instanceof Ratio || $value instanceof BigInt || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot use %s as a rational operand', get_debug_type($value)),
        );
    }

    public static function collapseBigInt(BigInt $value): BigInt|int
    {
        return $value->fitsInPhpInt() ? $value->toInt() : $value;
    }

    public static function toIntExponent(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof BigInt) {
            if (!$value->fitsInPhpInt()) {
                throw new InvalidArgumentException('Exponent does not fit in PHP int range');
            }

            return $value->toInt();
        }

        throw new InvalidArgumentException(
            sprintf('Cannot use %s as an integer exponent', get_debug_type($value)),
        );
    }

    public static function truncateToInt(mixed $value): mixed
    {
        if (is_int($value) || $value instanceof BigInt) {
            return $value;
        }

        if ($value instanceof Ratio) {
            return self::collapseBigInt($value->numerator()->divide($value->denominator()));
        }

        if (is_float($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot truncate %s to an integer', get_debug_type($value)),
        );
    }
}
