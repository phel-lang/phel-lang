<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;

use function abs;
use function ceil;
use function fdiv;
use function floor;
use function intdiv;
use function is_float;
use function is_int;

/**
 * Runtime numeric dispatch for `+ - * /` and friends across native PHP
 * numbers, {@see BigInt}, and {@see Ratio}. Native PHP operators
 * cannot dispatch on objects, so the compiler routes Phel arithmetic
 * through these helpers.
 *
 * Type lifting and validation live in {@see NumericCoercion}; native-int
 * overflow detection lives in {@see IntegerOverflow}. This class owns only
 * the contagion ladders.
 *
 * Contagion rules (left to right yields the result type):
 *
 *  - any op float                        -> float (highest priority; BigDecimal
 *                                                  cannot represent Inf/NaN)
 *  - BigDecimal op BigDecimal/int        -> BigDecimal
 *  - Ratio op Ratio/int/BigInt -> Ratio (auto-collapsed)
 *  - BigInt op BigInt/int        -> BigInt (auto-collapsed)
 *  - int op int                          -> int (or Ratio for non-integer divisions)
 */
final class NumericOperations
{
    public static function add(mixed $a, mixed $b): mixed
    {
        // Fast path for the overwhelmingly common case: two native ints.
        // `is_int` doubles as the numeric check, so it skips `ensureNumeric`
        // and the whole `is_float`/`instanceof` ladder below.
        if (is_int($a) && is_int($b)) {
            if (IntegerOverflow::onAdd($a, $b)) {
                return NumericCoercion::collapseBigInt(BigInt::fromInt($a)->add(BigInt::fromInt($b)));
            }

            return $a + $b;
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            // Float wins over BigDecimal: `(+ 1.0M 2.0)` returns a float, not a
            // BigDecimal. BigDecimal also cannot represent `##Inf`/`##NaN`, so a
            // non-finite float operand still routes here.
            return NumericCoercion::toFloat($a) + NumericCoercion::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return NumericCoercion::toBigDecimal($a)->add(NumericCoercion::toBigDecimal($b));
        }

        if ($a instanceof Ratio) {
            return $a->add(NumericCoercion::rationalOperand($b));
        }

        if ($b instanceof Ratio) {
            return $b->add(NumericCoercion::rationalOperand($a));
        }

        // int+int is handled by the fast path, so at least one operand is a
        // BigInt here; lift both and collapse the result back when it fits.
        return NumericCoercion::collapseBigInt(NumericCoercion::toBigInt($a)->add(NumericCoercion::toBigInt($b)));
    }

    public static function subtract(mixed $a, mixed $b): mixed
    {
        if (is_int($a) && is_int($b)) {
            if (IntegerOverflow::onSubtract($a, $b)) {
                return NumericCoercion::collapseBigInt(BigInt::fromInt($a)->subtract(BigInt::fromInt($b)));
            }

            return $a - $b;
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return NumericCoercion::toFloat($a) - NumericCoercion::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return NumericCoercion::toBigDecimal($a)->subtract(NumericCoercion::toBigDecimal($b));
        }

        if ($a instanceof Ratio) {
            return $a->subtract(NumericCoercion::rationalOperand($b));
        }

        if ($b instanceof Ratio) {
            // a - b == -(b - a)
            return self::negate($b->subtract(NumericCoercion::rationalOperand($a)));
        }

        return NumericCoercion::collapseBigInt(NumericCoercion::toBigInt($a)->subtract(NumericCoercion::toBigInt($b)));
    }

    public static function multiply(mixed $a, mixed $b): mixed
    {
        if (is_int($a) && is_int($b)) {
            if (IntegerOverflow::onMultiply($a, $b)) {
                return NumericCoercion::collapseBigInt(BigInt::fromInt($a)->multiply(BigInt::fromInt($b)));
            }

            return $a * $b;
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return NumericCoercion::toFloat($a) * NumericCoercion::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return NumericCoercion::toBigDecimal($a)->multiply(NumericCoercion::toBigDecimal($b));
        }

        if ($a instanceof Ratio) {
            return $a->multiply(NumericCoercion::rationalOperand($b));
        }

        if ($b instanceof Ratio) {
            return $b->multiply(NumericCoercion::rationalOperand($a));
        }

        return NumericCoercion::collapseBigInt(NumericCoercion::toBigInt($a)->multiply(NumericCoercion::toBigInt($b)));
    }

    /**
     * Division with rational promotion: int/int with non-zero remainder
     * returns a {@see Ratio} rather than a float.
     */
    public static function divide(mixed $a, mixed $b): mixed
    {
        if (is_int($a) && is_int($b)) {
            if ($b === 0) {
                throw new DivisionByZeroError('Division by zero');
            }

            if ($a % $b === 0) {
                return intdiv($a, $b);
            }

            return Ratio::create($a, $b);
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            $left = NumericCoercion::toFloat($a);
            $right = NumericCoercion::toFloat($b);
            // Route any float-over-zero division through `fdiv` so
            // `(/ 1.0 0)` => `INF`, `(/ -1.0 0)` => `-INF`, `(/ 0.0 0)` => `NAN`,
            // and `(/ ##Inf 0)` / `(/ ##NaN 0)` keep their IEEE-754 results.
            // Float wins over BigDecimal (`(/ 1.0M 2.0)` returns a float).
            if ($right === 0.0) {
                return fdiv($left, $right);
            }

            return $left / $right;
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return NumericCoercion::toBigDecimal($a)->divideExact(NumericCoercion::toBigDecimal($b));
        }

        if ($a instanceof Ratio) {
            return $a->divide(NumericCoercion::rationalOperand($b));
        }

        if ($b instanceof Ratio) {
            // a / (n/d) == (a * d) / n
            return self::divide(self::multiply($a, $b->denominator()), $b->numerator());
        }

        // int/int is handled by the fast path, so at least one BigInt operand
        // remains; an exact rational result is created and auto-collapsed.
        return Ratio::create(NumericCoercion::toBigInt($a), NumericCoercion::toBigInt($b));
    }

    public static function negate(mixed $a): mixed
    {
        NumericCoercion::ensureNumeric($a);

        if ($a instanceof Ratio) {
            return $a->negate();
        }

        if ($a instanceof BigInt) {
            return NumericCoercion::collapseBigInt($a->negate());
        }

        if ($a instanceof BigDecimal) {
            return $a->negate();
        }

        return -$a;
    }

    public static function compare(mixed $a, mixed $b): int
    {
        if (is_int($a) && is_int($b)) {
            return $a <=> $b;
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return NumericCoercion::toFloat($a) <=> NumericCoercion::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return NumericCoercion::toBigDecimal($a)->compareTo(NumericCoercion::toBigDecimal($b));
        }

        if ($a instanceof Ratio) {
            return $a->compareTo(NumericCoercion::rationalOperand($b));
        }

        if ($b instanceof Ratio) {
            return -$b->compareTo(NumericCoercion::rationalOperand($a));
        }

        return NumericCoercion::toBigInt($a)->compareTo(NumericCoercion::toBigInt($b));
    }

    public static function isEqual(mixed $a, mixed $b): bool
    {
        if (is_int($a) && is_int($b)) {
            return $a === $b;
        }

        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::compare($a, $b) === 0;
        }

        if (is_float($a) || is_float($b)) {
            return NumericCoercion::toFloat($a) === NumericCoercion::toFloat($b);
        }

        if ($a instanceof Ratio) {
            return $a->equals($b);
        }

        if ($b instanceof Ratio) {
            return $b->equals($a);
        }

        return NumericCoercion::toBigInt($a)->equals(NumericCoercion::toBigInt($b));
    }

    public static function isZero(mixed $a): bool
    {
        if ($a instanceof Ratio) {
            // Ratio::create collapses true zeros to int 0, so a Ratio
            // instance is always non-zero.
            return false;
        }

        if ($a instanceof BigInt) {
            return $a->isZero();
        }

        if ($a instanceof BigDecimal) {
            return $a->isZero();
        }

        return $a === 0 || $a === 0.0;
    }

    public static function abs(mixed $a): mixed
    {
        NumericCoercion::ensureNumeric($a);

        if ($a instanceof Ratio) {
            return $a->abs();
        }

        if ($a instanceof BigInt) {
            return NumericCoercion::collapseBigInt($a->abs());
        }

        if ($a instanceof BigDecimal) {
            return $a->abs();
        }

        // |PHP_INT_MIN| overflows the PHP int range; native abs() drops
        // to float, so promote to BigInt to preserve exactness.
        if ($a === PHP_INT_MIN) {
            return BigInt::fromInt($a)->abs();
        }

        return abs($a);
    }

    public static function power(mixed $base, mixed $exp): mixed
    {
        NumericCoercion::ensureNumeric($base);
        NumericCoercion::ensureNumeric($exp);

        // Float exponent or float base falls back to native ** for parity
        // with PHP semantics, including fractional exponents.
        if (is_float($base) || is_float($exp) || $exp instanceof Ratio) {
            return NumericCoercion::toFloat($base) ** NumericCoercion::toFloat($exp);
        }

        $exponent = NumericCoercion::toIntExponent($exp);

        if ($base instanceof Ratio) {
            return self::rationalPower($base, $exponent);
        }

        // Route int^int and BigInt^int through BigInt so overflow
        // auto-promotes; cheap exponents collapse back to int.
        $baseBig = NumericCoercion::toBigInt($base);

        if ($exponent < 0) {
            return Ratio::create(BigInt::one(), $baseBig->pow(-$exponent));
        }

        return NumericCoercion::collapseBigInt($baseBig->pow($exponent));
    }

    /**
     * Truncated integer quotient. Float operands keep PHP's `intdiv` semantics.
     */
    public static function quot(mixed $a, mixed $b): mixed
    {
        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (self::isZero($b)) {
            throw new DivisionByZeroError('Division by zero');
        }

        if (is_float($a) || is_float($b)) {
            $ratio = NumericCoercion::toFloat($a) / NumericCoercion::toFloat($b);
            // Float operands keep float result; truncate toward zero.
            // Float wins over BigDecimal (`(quot 1.0M 1.0)` returns a float).
            return $ratio < 0 ? ceil($ratio) : floor($ratio);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::truncateBigDecimalQuot(
                NumericCoercion::toBigDecimal($a),
                NumericCoercion::toBigDecimal($b),
            );
        }

        if ($a instanceof Ratio || $b instanceof Ratio) {
            $quotient = self::divide($a, $b);
            return NumericCoercion::truncateToInt($quotient);
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return NumericCoercion::collapseBigInt(NumericCoercion::toBigInt($a)->divide(NumericCoercion::toBigInt($b)));
        }

        return intdiv($a, $b);
    }

    /**
     * Truncated remainder: matches PHP's `%`, sign follows dividend.
     */
    public static function rem(mixed $a, mixed $b): mixed
    {
        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (self::isZero($b)) {
            throw new DivisionByZeroError('Division by zero');
        }

        if (is_float($a) || is_float($b)) {
            // Float wins over BigDecimal (`(rem 1.0M 1.0)` returns a float).
            $q = NumericCoercion::toFloat(self::quot($a, $b));
            return NumericCoercion::toFloat($a) - (NumericCoercion::toFloat($b) * $q);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            $aBig = NumericCoercion::toBigDecimal($a);
            $bBig = NumericCoercion::toBigDecimal($b);
            $q = self::truncateBigDecimalQuot($aBig, $bBig);
            return $aBig->subtract($bBig->multiply($q));
        }

        if ($a instanceof Ratio || $b instanceof Ratio) {
            $q = self::quot($a, $b);
            return self::subtract($a, self::multiply($b, $q));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            $aBig = NumericCoercion::toBigInt($a);
            $bBig = NumericCoercion::toBigInt($b);
            $q = $aBig->divide($bBig);
            return NumericCoercion::collapseBigInt($aBig->subtract($bBig->multiply($q)));
        }

        return ($a) % ($b);
    }

    /**
     * Floor-modulo: result has the same sign as the divisor, matching
     * Phel's prior `mod` semantics.
     */
    public static function mod(mixed $a, mixed $b): mixed
    {
        NumericCoercion::ensureNumeric($a);
        NumericCoercion::ensureNumeric($b);

        if (self::isZero($b)) {
            throw new DivisionByZeroError('Modulo by zero');
        }

        $rem = self::rem($a, $b);

        if (self::isZero($rem)) {
            return $rem;
        }

        // Adjust when sign of remainder differs from sign of divisor.
        $remSign = self::compare($rem, 0);
        $divSign = self::compare($b, 0);
        if ($remSign !== $divSign) {
            return self::add($rem, $b);
        }

        return $rem;
    }

    /**
     * Truncates `a / b` toward zero and returns the result as a scale-0
     * `BigDecimal`. Used by `quot` and `rem` for BigDecimal operands.
     */
    private static function truncateBigDecimalQuot(BigDecimal $a, BigDecimal $b): BigDecimal
    {
        $scale = max($a->scale(), $b->scale());
        $aLifted = $a->mantissa()->multiply(BigInt::fromInt(10)->pow($scale - $a->scale()));
        $bLifted = $b->mantissa()->multiply(BigInt::fromInt(10)->pow($scale - $b->scale()));

        return BigDecimal::fromBigInt($aLifted->divide($bLifted));
    }

    private static function rationalPower(Ratio $base, int $exponent): mixed
    {
        if ($exponent === 0) {
            return 1;
        }

        if ($exponent < 0) {
            // (n/d)^(-k) = (d/n)^k.
            $reciprocal = Ratio::create($base->denominator(), $base->numerator());
            if ($reciprocal instanceof Ratio) {
                return self::rationalPower($reciprocal, -$exponent);
            }

            return self::power($reciprocal, -$exponent);
        }

        $num = $base->numerator()->pow($exponent);
        $den = $base->denominator()->pow($exponent);
        return Ratio::create($num, $den);
    }
}
