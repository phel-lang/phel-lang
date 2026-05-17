<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;
use InvalidArgumentException;

use function abs;
use function ceil;
use function fdiv;
use function floor;
use function intdiv;
use function is_float;
use function is_int;
use function sprintf;

/**
 * Runtime numeric dispatch for `+ - * /` and friends across native PHP
 * numbers, {@see BigInt}, and {@see Rational}. Native PHP operators
 * cannot dispatch on objects, so the compiler routes Phel arithmetic
 * through these helpers.
 *
 * Contagion rules (left to right yields the result type):
 *
 *  - any op float                        -> float (highest priority; BigDecimal
 *                                                  cannot represent Inf/NaN)
 *  - BigDecimal op BigDecimal/int        -> BigDecimal
 *  - Rational op Rational/int/BigInt -> Rational (auto-collapsed)
 *  - BigInt op BigInt/int        -> BigInt (auto-collapsed)
 *  - int op int                          -> int (or Rational for non-integer divisions)
 */
final class NumericOperations
{
    public static function add(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            // Float wins over BigDecimal: `(+ 1.0M 2.0)` returns a float, not a
            // BigDecimal. BigDecimal also cannot represent `##Inf`/`##NaN`, so a
            // non-finite float operand still routes here.
            return self::toFloat($a) + self::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::toBigDecimal($a)->add(self::toBigDecimal($b));
        }

        if ($a instanceof Rational) {
            return $a->add(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return $b->add(self::rationalOperand($a));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::collapseBigInt(self::toBigInt($a)->add(self::toBigInt($b)));
        }

        if (self::addOverflows($a, $b)) {
            return self::collapseBigInt(BigInt::fromInt($a)->add(BigInt::fromInt($b)));
        }

        return $a + $b;
    }

    public static function subtract(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) - self::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::toBigDecimal($a)->subtract(self::toBigDecimal($b));
        }

        if ($a instanceof Rational) {
            return $a->subtract(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            // a - b == -(b - a)
            return self::negate($b->subtract(self::rationalOperand($a)));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::collapseBigInt(self::toBigInt($a)->subtract(self::toBigInt($b)));
        }

        if (self::subtractOverflows($a, $b)) {
            return self::collapseBigInt(BigInt::fromInt($a)->subtract(BigInt::fromInt($b)));
        }

        return $a - $b;
    }

    public static function multiply(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) * self::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::toBigDecimal($a)->multiply(self::toBigDecimal($b));
        }

        if ($a instanceof Rational) {
            return $a->multiply(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return $b->multiply(self::rationalOperand($a));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::collapseBigInt(self::toBigInt($a)->multiply(self::toBigInt($b)));
        }

        if (self::multiplyOverflows($a, $b)) {
            return self::collapseBigInt(BigInt::fromInt($a)->multiply(BigInt::fromInt($b)));
        }

        return $a * $b;
    }

    /**
     * Division with rational promotion: int/int with non-zero remainder
     * returns a {@see Rational} rather than a float.
     */
    public static function divide(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            $left = self::toFloat($a);
            $right = self::toFloat($b);
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
            return self::toBigDecimal($a)->divideExact(self::toBigDecimal($b));
        }

        if ($a instanceof Rational) {
            return $a->divide(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            // a / (n/d) == (a * d) / n
            return self::divide(self::multiply($a, $b->denominator()), $b->numerator());
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return Rational::create(self::toBigInt($a), self::toBigInt($b));
        }

        if ($b === 0) {
            throw new DivisionByZeroError('Division by zero');
        }

        if ($a % $b === 0) {
            return intdiv($a, $b);
        }

        return Rational::create($a, $b);
    }

    public static function negate(mixed $a): mixed
    {
        self::ensureNumeric($a);

        if ($a instanceof Rational) {
            return $a->negate();
        }

        if ($a instanceof BigInt) {
            return self::collapseBigInt($a->negate());
        }

        if ($a instanceof BigDecimal) {
            return $a->negate();
        }

        return -$a;
    }

    public static function compare(mixed $a, mixed $b): int
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) <=> self::toFloat($b);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::toBigDecimal($a)->compareTo(self::toBigDecimal($b));
        }

        if ($a instanceof Rational) {
            return $a->compareTo(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return -$b->compareTo(self::rationalOperand($a));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::toBigInt($a)->compareTo(self::toBigInt($b));
        }

        return $a <=> $b;
    }

    public static function isEqual(mixed $a, mixed $b): bool
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::compare($a, $b) === 0;
        }

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) === self::toFloat($b);
        }

        if ($a instanceof Rational) {
            return $a->equals($b);
        }

        if ($b instanceof Rational) {
            return $b->equals($a);
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::toBigInt($a)->equals(self::toBigInt($b));
        }

        return $a === $b;
    }

    public static function isZero(mixed $a): bool
    {
        if ($a instanceof Rational) {
            // Rational::create collapses true zeros to int 0, so a Rational
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
        self::ensureNumeric($a);

        if ($a instanceof Rational) {
            return $a->abs();
        }

        if ($a instanceof BigInt) {
            return self::collapseBigInt($a->abs());
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
        self::ensureNumeric($base);
        self::ensureNumeric($exp);

        // Float exponent or float base falls back to native ** for parity
        // with PHP semantics, including fractional exponents.
        if (is_float($base) || is_float($exp) || $exp instanceof Rational) {
            return self::toFloat($base) ** self::toFloat($exp);
        }

        $exponent = self::toIntExponent($exp);

        if ($base instanceof Rational) {
            return self::rationalPower($base, $exponent);
        }

        // Route int^int and BigInt^int through BigInt so overflow
        // auto-promotes; cheap exponents collapse back to int.
        $baseBig = self::toBigInt($base);

        if ($exponent < 0) {
            return Rational::create(BigInt::one(), $baseBig->pow(-$exponent));
        }

        return self::collapseBigInt($baseBig->pow($exponent));
    }

    /**
     * Truncated integer quotient. Float operands keep PHP's `intdiv` semantics.
     */
    public static function quot(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (self::isZero($b)) {
            throw new DivisionByZeroError('Division by zero');
        }

        if (is_float($a) || is_float($b)) {
            $ratio = self::toFloat($a) / self::toFloat($b);
            // Float operands keep float result; truncate toward zero.
            // Float wins over BigDecimal (`(quot 1.0M 1.0)` returns a float).
            return $ratio < 0 ? ceil($ratio) : floor($ratio);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            return self::truncateBigDecimalQuot(
                self::toBigDecimal($a),
                self::toBigDecimal($b),
            );
        }

        if ($a instanceof Rational || $b instanceof Rational) {
            $quotient = self::divide($a, $b);
            return self::truncateToInt($quotient);
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            return self::collapseBigInt(self::toBigInt($a)->divide(self::toBigInt($b)));
        }

        return intdiv((int) $a, (int) $b);
    }

    /**
     * Truncated remainder: matches PHP's `%`, sign follows dividend.
     */
    public static function rem(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (self::isZero($b)) {
            throw new DivisionByZeroError('Division by zero');
        }

        if (is_float($a) || is_float($b)) {
            // Float wins over BigDecimal (`(rem 1.0M 1.0)` returns a float).
            $q = (float) self::quot($a, $b);
            return self::toFloat($a) - (self::toFloat($b) * $q);
        }

        if ($a instanceof BigDecimal || $b instanceof BigDecimal) {
            $aBig = self::toBigDecimal($a);
            $bBig = self::toBigDecimal($b);
            $q = self::truncateBigDecimalQuot($aBig, $bBig);
            return $aBig->subtract($bBig->multiply($q));
        }

        if ($a instanceof Rational || $b instanceof Rational) {
            $q = self::quot($a, $b);
            return self::subtract($a, self::multiply($b, $q));
        }

        if ($a instanceof BigInt || $b instanceof BigInt) {
            $aBig = self::toBigInt($a);
            $bBig = self::toBigInt($b);
            $q = $aBig->divide($bBig);
            return self::collapseBigInt($aBig->subtract($bBig->multiply($q)));
        }

        return ((int) $a) % ((int) $b);
    }

    /**
     * Floor-modulo: result has the same sign as the divisor, matching
     * Phel's prior `mod` semantics.
     */
    public static function mod(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

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

    private static function addOverflows(int $a, int $b): bool
    {
        if ($a > 0 && $b > 0) {
            return $a > PHP_INT_MAX - $b;
        }

        if ($a < 0 && $b < 0) {
            return $a < PHP_INT_MIN - $b;
        }

        return false;
    }

    private static function subtractOverflows(int $a, int $b): bool
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

    private static function multiplyOverflows(int $a, int $b): bool
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

    private static function ensureNumeric(mixed $value): void
    {
        if (is_int($value) || is_float($value)) {
            return;
        }

        if ($value instanceof BigInt || $value instanceof Rational || $value instanceof BigDecimal) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf('Expected a number, got %s', get_debug_type($value)),
        );
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

    private static function toBigDecimal(mixed $value): BigDecimal
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

        if ($value instanceof Rational) {
            return BigDecimal::fromBigInt($value->numerator())
                ->divideExact(BigDecimal::fromBigInt($value->denominator()));
        }

        throw new InvalidArgumentException(
            sprintf('Cannot lift %s to BigDecimal', get_debug_type($value)),
        );
    }

    private static function toFloat(mixed $value): float
    {
        if ($value instanceof Rational) {
            return $value->toFloat();
        }

        if ($value instanceof BigInt || $value instanceof BigDecimal) {
            return (float) (string) $value;
        }

        return (float) $value;
    }

    private static function toBigInt(mixed $value): BigInt
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

    private static function rationalOperand(mixed $value): Rational|BigInt|int
    {
        if ($value instanceof Rational || $value instanceof BigInt || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot use %s as a rational operand', get_debug_type($value)),
        );
    }

    private static function collapseBigInt(BigInt $value): BigInt|int
    {
        return $value->fitsInPhpInt() ? $value->toInt() : $value;
    }

    private static function rationalPower(Rational $base, int $exponent): mixed
    {
        if ($exponent === 0) {
            return 1;
        }

        if ($exponent < 0) {
            // (n/d)^(-k) = (d/n)^k.
            $reciprocal = Rational::create($base->denominator(), $base->numerator());
            if ($reciprocal instanceof Rational) {
                return self::rationalPower($reciprocal, -$exponent);
            }

            return self::power($reciprocal, -$exponent);
        }

        $num = $base->numerator()->pow($exponent);
        $den = $base->denominator()->pow($exponent);
        return Rational::create($num, $den);
    }

    private static function toIntExponent(mixed $value): int
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

    private static function truncateToInt(mixed $value): mixed
    {
        if (is_int($value) || $value instanceof BigInt) {
            return $value;
        }

        if ($value instanceof Rational) {
            return self::collapseBigInt($value->numerator()->divide($value->denominator()));
        }

        return (int) $value;
    }
}
