<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use DivisionByZeroError;
use Phel\Lang\BigInteger;
use Phel\Lang\Rational;
use PHPUnit\Framework\TestCase;

final class RationalTest extends TestCase
{
    public function test_create_simple(): void
    {
        $r = Rational::create(1, 2);

        self::assertInstanceOf(Rational::class, $r);
        self::assertSame('1', (string) $r->numerator());
        self::assertSame('2', (string) $r->denominator());
        self::assertSame('1/2', (string) $r);
    }

    public function test_create_normalises_negative_denominator(): void
    {
        $r = Rational::create(1, -2);

        self::assertInstanceOf(Rational::class, $r);
        self::assertSame('-1', (string) $r->numerator());
        self::assertSame('2', (string) $r->denominator());
    }

    public function test_create_normalises_double_negative(): void
    {
        $r = Rational::create(-1, -2);

        self::assertInstanceOf(Rational::class, $r);
        self::assertSame('1', (string) $r->numerator());
        self::assertSame('2', (string) $r->denominator());
    }

    public function test_create_reduces_via_gcd(): void
    {
        $r = Rational::create(6, 4);

        self::assertInstanceOf(Rational::class, $r);
        self::assertSame('3', (string) $r->numerator());
        self::assertSame('2', (string) $r->denominator());
    }

    public function test_create_zero_numerator_collapses_to_int_zero(): void
    {
        self::assertSame(0, Rational::create(0, 5));
        self::assertSame(0, Rational::create(0, -5));
    }

    public function test_create_collapses_when_denominator_one(): void
    {
        self::assertSame(2, Rational::create(4, 2));
        self::assertSame(8, Rational::create(8, 1));
        self::assertSame(-3, Rational::create(-6, 2));
    }

    public function test_create_collapses_to_big_integer_when_too_large(): void
    {
        $big = BigInteger::fromString('123456789012345678901234567890');
        $result = Rational::create($big, 1);

        self::assertInstanceOf(BigInteger::class, $result);
        self::assertSame('123456789012345678901234567890', (string) $result);
    }

    public function test_create_zero_denominator_throws(): void
    {
        $this->expectException(DivisionByZeroError::class);
        Rational::create(1, 0);
    }

    public function test_create_accepts_big_integers(): void
    {
        $num = BigInteger::fromString('1000000000000');
        $den = BigInteger::fromString('500000000000');

        self::assertSame(2, Rational::create($num, $den));
    }

    public function test_add_rational_to_rational(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(1, 3);
        $sum = $a->add($b);

        self::assertInstanceOf(Rational::class, $sum);
        self::assertSame('5/6', (string) $sum);
    }

    public function test_add_collapses_when_result_integer(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(1, 2);

        self::assertSame(1, $a->add($b));
    }

    public function test_add_with_int(): void
    {
        $a = Rational::create(1, 2);

        $result = $a->add(2);
        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('5/2', (string) $result);
    }

    public function test_add_with_big_integer(): void
    {
        $a = Rational::create(1, 2);
        $big = BigInteger::fromInt(3);

        $result = $a->add($big);
        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('7/2', (string) $result);
    }

    public function test_subtract(): void
    {
        $a = Rational::create(3, 4);
        $b = Rational::create(1, 4);
        $expected = Rational::create(1, 2);

        $result = $a->subtract($b);
        self::assertInstanceOf(Rational::class, $result);
        self::assertTrue($expected->equals($result));
    }

    public function test_subtract_collapses_to_zero(): void
    {
        $a = Rational::create(1, 2);

        self::assertSame(0, $a->subtract($a));
    }

    public function test_multiply(): void
    {
        $a = Rational::create(2, 3);
        $b = Rational::create(3, 4);

        $result = $a->multiply($b);
        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/2', (string) $result);
    }

    public function test_multiply_collapses_to_int(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(2, 1);

        self::assertSame(1, $a->multiply($b));
    }

    public function test_divide(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(1, 4);

        self::assertSame(2, $a->divide($b));
    }

    public function test_divide_with_int(): void
    {
        $a = Rational::create(1, 2);

        $result = $a->divide(3);
        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1/6', (string) $result);
    }

    public function test_divide_by_zero_throws(): void
    {
        $a = Rational::create(1, 2);

        $this->expectException(DivisionByZeroError::class);
        $a->divide(0);
    }

    public function test_negate(): void
    {
        $a = Rational::create(2, 3);

        self::assertSame('-2/3', (string) $a->negate());
        self::assertSame('2/3', (string) $a->negate()->negate());
    }

    public function test_abs(): void
    {
        self::assertSame('2/3', (string) Rational::create(-2, 3)->abs());
        self::assertSame('2/3', (string) Rational::create(2, 3)->abs());
    }

    public function test_compare_to_rational(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(2, 3);

        self::assertSame(-1, $a->compareTo($b));
        self::assertSame(1, $b->compareTo($a));
        self::assertSame(0, $a->compareTo(Rational::create(1, 2)));
    }

    public function test_compare_to_int(): void
    {
        $a = Rational::create(3, 2);

        self::assertSame(1, $a->compareTo(1));
        self::assertSame(-1, $a->compareTo(2));
    }

    public function test_compare_to_big_integer(): void
    {
        $a = Rational::create(3, 2);

        self::assertSame(1, $a->compareTo(BigInteger::fromInt(1)));
        self::assertSame(-1, $a->compareTo(BigInteger::fromInt(2)));
    }

    public function test_equals_canonical_form(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(2, 4);

        self::assertTrue($a->equals($b));
    }

    public function test_equals_distinct_canonical_forms_not_equal(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(1, 3);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_non_rational(): void
    {
        $a = Rational::create(1, 2);

        self::assertFalse($a->equals('1/2'));
        self::assertFalse($a->equals(null));
        self::assertFalse($a->equals(0.5));
    }

    public function test_hash_equal_for_equal_canonical_forms(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(2, 4);

        self::assertSame($a->hash(), $b->hash());
    }

    public function test_hash_distinct_for_distinct_forms(): void
    {
        $a = Rational::create(1, 2);
        $b = Rational::create(1, 3);

        self::assertNotSame($a->hash(), $b->hash());
    }

    public function test_to_float(): void
    {
        self::assertSame(0.5, Rational::create(1, 2)->toFloat());
        self::assertSame(-0.25, Rational::create(-1, 4)->toFloat());
        self::assertEqualsWithDelta(1.0 / 3.0, Rational::create(1, 3)->toFloat(), 1e-15);
    }

    public function test_to_string_format(): void
    {
        self::assertSame('1/2', (string) Rational::create(1, 2));
        self::assertSame('-1/2', (string) Rational::create(-1, 2));
        self::assertSame('22/7', (string) Rational::create(22, 7));
    }

    public function test_arithmetic_with_big_integer_arguments(): void
    {
        $a = Rational::create(1, 3);
        $bigOther = BigInteger::fromString('1000000000000000000');

        $result = $a->multiply($bigOther);
        self::assertInstanceOf(Rational::class, $result);
        self::assertSame('1000000000000000000/3', (string) $result);
    }

    public function test_arithmetic_no_overflow_with_large_values(): void
    {
        $big = BigInteger::fromString('100000000000000000');
        $a = Rational::create($big, 3);
        $b = Rational::create($big, 7);

        $result = $a->add($b);
        self::assertInstanceOf(Rational::class, $result);
        // (100e15/3) + (100e15/7) = 100e15 * 10 / 21 = 1e18 / 21
        self::assertSame('1000000000000000000/21', (string) $result);
    }
}
