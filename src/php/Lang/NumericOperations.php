<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;
use InvalidArgumentException;

use function abs;
use function fdiv;
use function intdiv;
use function is_finite;
use function is_float;
use function is_int;
use function sprintf;

/**
 * Runtime numeric dispatch for `+ - * /` and friends across native PHP
 * numbers, {@see BigInteger}, and {@see Rational}. Native PHP operators
 * cannot dispatch on objects, so the compiler routes Phel arithmetic
 * through these helpers.
 *
 * Contagion rules (left to right yields the result type):
 *
 *  - Rational op Rational/int/BigInteger -> Rational (auto-collapsed)
 *  - Rational op float                   -> float
 *  - BigInteger op BigInteger/int        -> BigInteger (auto-collapsed)
 *  - BigInteger op float                 -> float
 *  - int op int                          -> int (or Rational for non-integer divisions)
 *  - any op float                        -> float
 */
final class NumericOperations
{
    public static function add(mixed $a, mixed $b): mixed
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) + self::toFloat($b);
        }

        if ($a instanceof Rational) {
            return $a->add(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return $b->add(self::rationalOperand($a));
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
            return self::collapseBigInt(self::toBigInt($a)->add(self::toBigInt($b)));
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

        if ($a instanceof Rational) {
            return $a->subtract(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            // a - b == -(b - a)
            return self::negate($b->subtract(self::rationalOperand($a)));
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
            return self::collapseBigInt(self::toBigInt($a)->subtract(self::toBigInt($b)));
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

        if ($a instanceof Rational) {
            return $a->multiply(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return $b->multiply(self::rationalOperand($a));
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
            return self::collapseBigInt(self::toBigInt($a)->multiply(self::toBigInt($b)));
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
            // Preserve PHP's `fdiv` semantics for Inf/NaN over zero so that
            // `(/ ##Inf 0) => Inf` and `(/ ##NaN 0) => NaN` keep working.
            if ($right === 0.0 && !is_finite($left)) {
                return fdiv($left, $right);
            }

            return $left / $right;
        }

        if ($a instanceof Rational) {
            return $a->divide(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            // a / (n/d) == (a * d) / n
            return self::divide(self::multiply($a, $b->denominator()), $b->numerator());
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
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

        if ($a instanceof BigInteger) {
            return self::collapseBigInt($a->negate());
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

        if ($a instanceof Rational) {
            return $a->compareTo(self::rationalOperand($b));
        }

        if ($b instanceof Rational) {
            return -$b->compareTo(self::rationalOperand($a));
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
            return self::toBigInt($a)->compareTo(self::toBigInt($b));
        }

        return $a <=> $b;
    }

    public static function isEqual(mixed $a, mixed $b): bool
    {
        self::ensureNumeric($a);
        self::ensureNumeric($b);

        if (is_float($a) || is_float($b)) {
            return self::toFloat($a) === self::toFloat($b);
        }

        if ($a instanceof Rational) {
            return $a->equals($b);
        }

        if ($b instanceof Rational) {
            return $b->equals($a);
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
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

        if ($a instanceof BigInteger) {
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

        if ($a instanceof BigInteger) {
            return self::collapseBigInt($a->abs());
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

        if ($base instanceof BigInteger) {
            if ($exponent < 0) {
                return Rational::create(BigInteger::one(), $base->pow(-$exponent));
            }

            return self::collapseBigInt($base->pow($exponent));
        }

        if ($exponent < 0) {
            return Rational::create(1, BigInteger::fromInt($base)->pow(-$exponent));
        }

        return $base ** $exponent;
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
            return (int) $ratio;
        }

        if ($a instanceof Rational || $b instanceof Rational) {
            $quotient = self::divide($a, $b);
            return self::truncateToInt($quotient);
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
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
            $q = (float) self::quot($a, $b);
            return self::toFloat($a) - (self::toFloat($b) * $q);
        }

        if ($a instanceof Rational || $b instanceof Rational) {
            $q = self::quot($a, $b);
            return self::subtract($a, self::multiply($b, $q));
        }

        if ($a instanceof BigInteger || $b instanceof BigInteger) {
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

    private static function ensureNumeric(mixed $value): void
    {
        if (is_int($value) || is_float($value)) {
            return;
        }

        if ($value instanceof BigInteger || $value instanceof Rational) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf('Expected a number, got %s', get_debug_type($value)),
        );
    }

    private static function toFloat(mixed $value): float
    {
        if ($value instanceof Rational) {
            return $value->toFloat();
        }

        if ($value instanceof BigInteger) {
            return (float) (string) $value;
        }

        return (float) $value;
    }

    private static function toBigInt(mixed $value): BigInteger
    {
        if ($value instanceof BigInteger) {
            return $value;
        }

        if (is_int($value)) {
            return BigInteger::fromInt($value);
        }

        throw new InvalidArgumentException(
            sprintf('Cannot lift %s to BigInteger', get_debug_type($value)),
        );
    }

    private static function rationalOperand(mixed $value): Rational|BigInteger|int
    {
        if ($value instanceof Rational || $value instanceof BigInteger || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot use %s as a rational operand', get_debug_type($value)),
        );
    }

    private static function collapseBigInt(BigInteger $value): BigInteger|int
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

        if ($value instanceof BigInteger) {
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
        if (is_int($value) || $value instanceof BigInteger) {
            return $value;
        }

        if ($value instanceof Rational) {
            return self::collapseBigInt($value->numerator()->divide($value->denominator()));
        }

        return (int) $value;
    }
}
