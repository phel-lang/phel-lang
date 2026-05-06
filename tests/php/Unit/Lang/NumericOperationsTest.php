<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use DivisionByZeroError;
use InvalidArgumentException;
use Phel\Lang\BigInteger;
use Phel\Lang\NumericOperations;
use Phel\Lang\Rational;
use PHPUnit\Framework\TestCase;

final class NumericOperationsTest extends TestCase
{
    public function test_add_int_int(): void
    {
        self::assertSame(5, NumericOperations::add(2, 3));
    }

    public function test_add_int_float(): void
    {
        self::assertSame(2.5, NumericOperations::add(1, 1.5));
    }

    public function test_add_rational_rational(): void
    {
        $half = Rational::create(1, 2);
        $third = Rational::create(1, 3);

        $result = NumericOperations::add($half, $third);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('5/6', (string) $result);
    }

    public function test_add_rational_int_collapses_to_int(): void
    {
        $half = Rational::create(1, 2);

        $result = NumericOperations::add($half, $half);

        self::assertSame(1, $result);
    }

    public function test_add_int_rational_is_commutative(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame((string) NumericOperations::add($half, 3), (string) NumericOperations::add(3, $half));
    }

    public function test_add_rational_float_returns_float(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame(1.5, NumericOperations::add($half, 1.0));
    }

    public function test_add_bigint_int(): void
    {
        $big = BigInteger::fromInt(10);

        self::assertSame(13, NumericOperations::add($big, 3));
    }

    public function test_subtract_rational(): void
    {
        $half = Rational::create(1, 2);
        $third = Rational::create(1, 3);

        $result = NumericOperations::subtract($half, $third);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/6', (string) $result);
    }

    public function test_subtract_int_rational(): void
    {
        $half = Rational::create(1, 2);

        $result = NumericOperations::subtract(1, $half);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/2', (string) $result);
    }

    public function test_multiply_rational(): void
    {
        $half = Rational::create(1, 2);
        $third = Rational::create(1, 3);

        $result = NumericOperations::multiply($half, $third);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/6', (string) $result);
    }

    public function test_multiply_int_rational_collapses(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame(1, NumericOperations::multiply($half, 2));
    }

    public function test_divide_int_int_exact(): void
    {
        self::assertSame(2, NumericOperations::divide(4, 2));
    }

    public function test_divide_int_int_inexact_returns_rational(): void
    {
        $result = NumericOperations::divide(1, 2);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/2', (string) $result);
    }

    public function test_divide_negative_int_int(): void
    {
        $result = NumericOperations::divide(-1, 2);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('-1/2', (string) $result);
    }

    public function test_divide_int_zero_throws(): void
    {
        $this->expectException(DivisionByZeroError::class);
        NumericOperations::divide(1, 0);
    }

    public function test_divide_float_zero_returns_inf(): void
    {
        self::assertSame(INF, NumericOperations::divide(INF, 0));
    }

    public function test_divide_finite_float_by_zero_returns_inf(): void
    {
        $result = NumericOperations::divide(1.0, 0.0);
        self::assertTrue(is_infinite($result) && $result > 0);
    }

    public function test_divide_negative_finite_float_by_zero_returns_negative_inf(): void
    {
        $result = NumericOperations::divide(-1.0, 0.0);
        self::assertTrue(is_infinite($result) && $result < 0);
    }

    public function test_divide_zero_float_by_zero_returns_nan(): void
    {
        self::assertNan(NumericOperations::divide(0.0, 0.0));
    }

    public function test_divide_int_rational(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame(2, NumericOperations::divide(1, $half));
    }

    public function test_divide_bigint(): void
    {
        $a = BigInteger::fromInt(10);
        $b = BigInteger::fromInt(4);

        $result = NumericOperations::divide($a, $b);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('5/2', (string) $result);
    }

    public function test_negate_int(): void
    {
        self::assertSame(-1, NumericOperations::negate(1));
    }

    public function test_negate_rational(): void
    {
        $half = Rational::create(1, 2);

        $result = NumericOperations::negate($half);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('-1/2', (string) $result);
    }

    public function test_compare_rational_int(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame(-1, NumericOperations::compare($half, 1));
        self::assertSame(0, NumericOperations::compare($half, Rational::create(2, 4)));
        self::assertSame(1, NumericOperations::compare(1, $half));
    }

    public function test_compare_mixed_with_float(): void
    {
        $half = Rational::create(1, 2);

        self::assertSame(0, NumericOperations::compare($half, 0.5));
    }

    public function test_is_equal_rational_int(): void
    {
        $one = Rational::create(2, 2); // collapses to int 1
        self::assertSame(1, $one);

        // Genuinely non-collapsed rational equality
        $half = Rational::create(1, 2);
        self::assertTrue(NumericOperations::isEqual($half, Rational::create(2, 4)));
        self::assertFalse(NumericOperations::isEqual($half, 1));
    }

    public function test_is_equal_int_int(): void
    {
        self::assertTrue(NumericOperations::isEqual(1, 1));
        self::assertFalse(NumericOperations::isEqual(1, 2));
    }

    public function test_is_zero(): void
    {
        self::assertTrue(NumericOperations::isZero(0));
        self::assertTrue(NumericOperations::isZero(0.0));
        self::assertFalse(NumericOperations::isZero(Rational::create(1, 2)));
        self::assertTrue(NumericOperations::isZero(BigInteger::fromInt(0)));
    }

    public function test_abs_rational(): void
    {
        $neg = Rational::create(-1, 2);

        $result = NumericOperations::abs($neg);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/2', (string) $result);
    }

    public function test_abs_int(): void
    {
        self::assertSame(5, NumericOperations::abs(-5));
    }

    public function test_power_int_int(): void
    {
        self::assertSame(8, NumericOperations::power(2, 3));
    }

    public function test_power_rational(): void
    {
        $half = Rational::create(1, 2);

        $result = NumericOperations::power($half, 2);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/4', (string) $result);
    }

    public function test_power_negative_exponent(): void
    {
        $result = NumericOperations::power(2, -1);

        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/2', (string) $result);
    }

    public function test_quot_int(): void
    {
        self::assertSame(3, NumericOperations::quot(10, 3));
    }

    public function test_quot_rational(): void
    {
        $half = Rational::create(7, 2); // 3.5

        self::assertSame(3, NumericOperations::quot($half, 1));
    }

    public function test_rem_int(): void
    {
        self::assertSame(1, NumericOperations::rem(10, 3));
        self::assertSame(-1, NumericOperations::rem(-10, 3));
    }

    public function test_mod_int(): void
    {
        self::assertSame(1, NumericOperations::mod(10, 3));
        // mod follows divisor sign; -10 mod 3 = 2 (not -1)
        self::assertSame(2, NumericOperations::mod(-10, 3));
    }

    public function test_ensures_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericOperations::add('not a number', 1);
    }

    public function test_add_int_int_overflow_promotes_to_bigint(): void
    {
        $result = NumericOperations::add(PHP_INT_MAX, 1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcadd((string) PHP_INT_MAX, '1'), (string) $result);
    }

    public function test_add_int_int_negative_overflow_promotes_to_bigint(): void
    {
        $result = NumericOperations::add(PHP_INT_MIN, -1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcadd((string) PHP_INT_MIN, '-1'), (string) $result);
    }

    public function test_subtract_int_int_overflow_promotes_to_bigint(): void
    {
        $result = NumericOperations::subtract(PHP_INT_MIN, 1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcsub((string) PHP_INT_MIN, '1'), (string) $result);
    }

    public function test_subtract_int_int_positive_overflow_promotes_to_bigint(): void
    {
        $result = NumericOperations::subtract(PHP_INT_MAX, -1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcsub((string) PHP_INT_MAX, '-1'), (string) $result);
    }

    public function test_multiply_int_int_overflow_promotes_to_bigint(): void
    {
        // 100000000 ^ 3 = 1e24, well outside PHP int range.
        $result = NumericOperations::multiply(NumericOperations::multiply(100000000, 100000000), 100000000);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame('1000000000000000000000000', (string) $result);
    }

    public function test_multiply_int_min_by_negative_one_promotes_to_bigint(): void
    {
        $result = NumericOperations::multiply(PHP_INT_MIN, -1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcmul((string) PHP_INT_MIN, '-1'), (string) $result);
    }

    public function test_multiply_int_int_within_range_stays_int(): void
    {
        self::assertSame(6, NumericOperations::multiply(2, 3));
        self::assertSame(0, NumericOperations::multiply(0, PHP_INT_MAX));
        self::assertSame(PHP_INT_MIN, NumericOperations::multiply(PHP_INT_MIN, 1));
        self::assertSame(PHP_INT_MIN, NumericOperations::multiply(1, PHP_INT_MIN));
    }

    public function test_multiply_int_min_by_two_promotes_to_bigint(): void
    {
        $result = NumericOperations::multiply(PHP_INT_MIN, 2);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame(bcmul((string) PHP_INT_MIN, '2'), (string) $result);
    }

    public function test_power_int_int_overflow_promotes_to_bigint(): void
    {
        $result = NumericOperations::power(2, 64);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame('18446744073709551616', (string) $result);
    }

    public function test_power_int_int_within_range_stays_int(): void
    {
        self::assertSame(8, NumericOperations::power(2, 3));
        self::assertSame(1, NumericOperations::power(1, 100));
    }
}
