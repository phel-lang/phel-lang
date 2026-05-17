<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use DivisionByZeroError;
use InvalidArgumentException;
use OverflowException;
use Phel\Lang\BigInt;
use PHPUnit\Framework\TestCase;

final class BigIntTest extends TestCase
{
    public function test_zero_factory(): void
    {
        $zero = BigInt::zero();

        self::assertSame('0', (string) $zero);
        self::assertTrue($zero->isZero());
        self::assertSame(0, $zero->signum());
    }

    public function test_one_factory(): void
    {
        $one = BigInt::one();

        self::assertSame('1', (string) $one);
        self::assertTrue($one->isOne());
        self::assertSame(1, $one->signum());
    }

    public function test_from_int_small(): void
    {
        self::assertSame('42', (string) BigInt::fromInt(42));
        self::assertSame('-42', (string) BigInt::fromInt(-42));
        self::assertSame('0', (string) BigInt::fromInt(0));
    }

    public function test_from_int_php_int_max(): void
    {
        self::assertSame((string) PHP_INT_MAX, (string) BigInt::fromInt(PHP_INT_MAX));
        self::assertSame((string) PHP_INT_MIN, (string) BigInt::fromInt(PHP_INT_MIN));
    }

    public function test_from_string_decimal(): void
    {
        self::assertSame('123456789012345678901234567890', (string) BigInt::fromString('123456789012345678901234567890'));
        self::assertSame('-987654321098765432109876543210', (string) BigInt::fromString('-987654321098765432109876543210'));
    }

    public function test_from_string_zero(): void
    {
        self::assertSame('0', (string) BigInt::fromString('0'));
        self::assertTrue(BigInt::fromString('0')->isZero());
    }

    public function test_from_string_negative_zero_is_zero(): void
    {
        self::assertSame('0', (string) BigInt::fromString('-0'));
        self::assertTrue(BigInt::fromString('-0')->isZero());
    }

    public function test_from_string_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('');
    }

    public function test_from_float_truncates_toward_zero(): void
    {
        self::assertSame('0', (string) BigInt::fromFloat(0.0));
        self::assertSame('0', (string) BigInt::fromFloat(0.9));
        self::assertSame('0', (string) BigInt::fromFloat(-0.9));
        self::assertSame('1', (string) BigInt::fromFloat(1.9));
        self::assertSame('-1', (string) BigInt::fromFloat(-1.9));
        self::assertSame('42', (string) BigInt::fromFloat(42.5));
        self::assertSame('-42', (string) BigInt::fromFloat(-42.5));
    }

    public function test_from_float_uses_shortest_round_trip_decimal(): void
    {
        // PHP_FLOAT_MAX renders as 1.7976931348623157e+308 (17 sig digits),
        // so the resulting integer has those 17 digits followed by 292 zeros.
        $result = BigInt::fromFloat(PHP_FLOAT_MAX);
        $expected = '17976931348623157' . str_repeat('0', 292);

        self::assertSame($expected, (string) $result);
    }

    public function test_from_float_rejects_nan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromFloat(NAN);
    }

    public function test_from_float_rejects_positive_infinity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromFloat(INF);
    }

    public function test_from_float_rejects_negative_infinity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromFloat(-INF);
    }

    public function test_from_string_rejects_only_minus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('-');
    }

    public function test_from_string_rejects_leading_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('012');
    }

    public function test_from_string_rejects_negative_leading_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('-012');
    }

    public function test_from_string_rejects_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('12a3');
    }

    public function test_from_string_rejects_plus_sign(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromString('+12');
    }

    public function test_add_small(): void
    {
        $result = BigInt::fromInt(2)->add(BigInt::fromInt(3));

        self::assertSame('5', (string) $result);
    }

    public function test_add_carry_across_digits(): void
    {
        $a = BigInt::fromString('999999999');
        $b = BigInt::fromString('1');

        self::assertSame('1000000000', (string) $a->add($b));
    }

    public function test_add_large(): void
    {
        $a = BigInt::fromString('123456789012345678901234567890');
        $b = BigInt::fromString('987654321098765432109876543210');

        self::assertSame('1111111110111111111011111111100', (string) $a->add($b));
    }

    public function test_add_commutative(): void
    {
        $a = BigInt::fromString('1234567890');
        $b = BigInt::fromString('9876543210');

        self::assertSame((string) $a->add($b), (string) $b->add($a));
    }

    public function test_add_negative_negative(): void
    {
        self::assertSame('-12', (string) BigInt::fromInt(-5)->add(BigInt::fromInt(-7)));
    }

    public function test_add_positive_negative_yields_difference(): void
    {
        self::assertSame('3', (string) BigInt::fromInt(10)->add(BigInt::fromInt(-7)));
        self::assertSame('-3', (string) BigInt::fromInt(7)->add(BigInt::fromInt(-10)));
        self::assertSame('0', (string) BigInt::fromInt(7)->add(BigInt::fromInt(-7)));
    }

    public function test_subtract_small(): void
    {
        self::assertSame('-1', (string) BigInt::fromInt(2)->subtract(BigInt::fromInt(3)));
        self::assertSame('1', (string) BigInt::fromInt(3)->subtract(BigInt::fromInt(2)));
    }

    public function test_subtract_large(): void
    {
        $a = BigInt::fromString('1000000000000000000000');
        $b = BigInt::fromString('1');

        self::assertSame('999999999999999999999', (string) $a->subtract($b));
    }

    public function test_subtract_negatives(): void
    {
        self::assertSame('2', (string) BigInt::fromInt(-5)->subtract(BigInt::fromInt(-7)));
        self::assertSame('-2', (string) BigInt::fromInt(-7)->subtract(BigInt::fromInt(-5)));
    }

    public function test_multiply_small(): void
    {
        self::assertSame('6', (string) BigInt::fromInt(2)->multiply(BigInt::fromInt(3)));
        self::assertSame('0', (string) BigInt::fromInt(0)->multiply(BigInt::fromInt(123)));
    }

    public function test_multiply_signs(): void
    {
        self::assertSame('-6', (string) BigInt::fromInt(-2)->multiply(BigInt::fromInt(3)));
        self::assertSame('-6', (string) BigInt::fromInt(2)->multiply(BigInt::fromInt(-3)));
        self::assertSame('6', (string) BigInt::fromInt(-2)->multiply(BigInt::fromInt(-3)));
    }

    public function test_multiply_large(): void
    {
        $a = BigInt::fromString('1000000000000000000');
        $b = BigInt::fromString('1000000000000000000');

        self::assertSame('1000000000000000000000000000000000000', (string) $a->multiply($b));
    }

    public function test_multiply_carry(): void
    {
        $a = BigInt::fromString('999999999');
        $b = BigInt::fromString('999999999');

        self::assertSame('999999998000000001', (string) $a->multiply($b));
    }

    public function test_divide_small(): void
    {
        self::assertSame('3', (string) BigInt::fromInt(10)->divide(BigInt::fromInt(3)));
        self::assertSame('5', (string) BigInt::fromInt(20)->divide(BigInt::fromInt(4)));
    }

    public function test_divide_truncates_toward_zero(): void
    {
        self::assertSame('-3', (string) BigInt::fromInt(-10)->divide(BigInt::fromInt(3)));
        self::assertSame('-3', (string) BigInt::fromInt(10)->divide(BigInt::fromInt(-3)));
        self::assertSame('3', (string) BigInt::fromInt(-10)->divide(BigInt::fromInt(-3)));
    }

    public function test_divide_zero_dividend(): void
    {
        self::assertSame('0', (string) BigInt::fromInt(0)->divide(BigInt::fromInt(5)));
    }

    public function test_divide_by_zero_throws(): void
    {
        $this->expectException(DivisionByZeroError::class);
        BigInt::fromInt(10)->divide(BigInt::zero());
    }

    public function test_divide_large(): void
    {
        $a = BigInt::fromString('1000000000000000000000000000000');
        $b = BigInt::fromString('1000000000');

        self::assertSame('1000000000000000000000', (string) $a->divide($b));
    }

    public function test_divide_multi_digit_divisor(): void
    {
        $a = BigInt::fromString('123456789012345678901234567890');
        $b = BigInt::fromString('987654321');

        self::assertSame('124999998873437499901', (string) $a->divide($b));
    }

    public function test_mod_small(): void
    {
        self::assertSame('1', (string) BigInt::fromInt(10)->mod(BigInt::fromInt(3)));
        self::assertSame('0', (string) BigInt::fromInt(20)->mod(BigInt::fromInt(4)));
    }

    public function test_mod_sign_follows_dividend(): void
    {
        self::assertSame('-1', (string) BigInt::fromInt(-10)->mod(BigInt::fromInt(3)));
        self::assertSame('1', (string) BigInt::fromInt(10)->mod(BigInt::fromInt(-3)));
        self::assertSame('-1', (string) BigInt::fromInt(-10)->mod(BigInt::fromInt(-3)));
    }

    public function test_mod_by_zero_throws(): void
    {
        $this->expectException(DivisionByZeroError::class);
        BigInt::fromInt(10)->mod(BigInt::zero());
    }

    public function test_gcd_coprimes(): void
    {
        self::assertSame('1', (string) BigInt::fromInt(7)->gcd(BigInt::fromInt(13)));
    }

    public function test_gcd_common(): void
    {
        self::assertSame('6', (string) BigInt::fromInt(12)->gcd(BigInt::fromInt(18)));
        self::assertSame('21', (string) BigInt::fromInt(1071)->gcd(BigInt::fromInt(462)));
    }

    public function test_gcd_zero_zero(): void
    {
        self::assertSame('0', (string) BigInt::zero()->gcd(BigInt::zero()));
    }

    public function test_gcd_with_zero(): void
    {
        self::assertSame('5', (string) BigInt::fromInt(5)->gcd(BigInt::zero()));
        self::assertSame('5', (string) BigInt::zero()->gcd(BigInt::fromInt(5)));
    }

    public function test_gcd_negative_signs_normalized(): void
    {
        self::assertSame('6', (string) BigInt::fromInt(-12)->gcd(BigInt::fromInt(18)));
        self::assertSame('6', (string) BigInt::fromInt(12)->gcd(BigInt::fromInt(-18)));
        self::assertSame('6', (string) BigInt::fromInt(-12)->gcd(BigInt::fromInt(-18)));
    }

    public function test_negate(): void
    {
        self::assertSame('-5', (string) BigInt::fromInt(5)->negate());
        self::assertSame('5', (string) BigInt::fromInt(-5)->negate());
        self::assertSame('0', (string) BigInt::zero()->negate());
    }

    public function test_abs(): void
    {
        self::assertSame('5', (string) BigInt::fromInt(-5)->abs());
        self::assertSame('5', (string) BigInt::fromInt(5)->abs());
        self::assertSame('0', (string) BigInt::zero()->abs());
    }

    public function test_pow_basic(): void
    {
        self::assertSame('1', (string) BigInt::fromInt(2)->pow(0));
        self::assertSame('2', (string) BigInt::fromInt(2)->pow(1));
        self::assertSame('1024', (string) BigInt::fromInt(2)->pow(10));
    }

    public function test_pow_large(): void
    {
        self::assertSame(
            '100000000000000000000',
            (string) BigInt::fromInt(10)->pow(20),
        );
    }

    public function test_pow_negative_base_signs(): void
    {
        self::assertSame('-8', (string) BigInt::fromInt(-2)->pow(3));
        self::assertSame('16', (string) BigInt::fromInt(-2)->pow(4));
    }

    public function test_pow_zero_to_zero_is_one(): void
    {
        self::assertSame('1', (string) BigInt::zero()->pow(0));
    }

    public function test_pow_negative_exponent_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigInt::fromInt(2)->pow(-1);
    }

    public function test_compare_to(): void
    {
        self::assertSame(-1, BigInt::fromInt(1)->compareTo(BigInt::fromInt(2)));
        self::assertSame(0, BigInt::fromInt(2)->compareTo(BigInt::fromInt(2)));
        self::assertSame(1, BigInt::fromInt(3)->compareTo(BigInt::fromInt(2)));
    }

    public function test_compare_to_signs(): void
    {
        self::assertSame(1, BigInt::fromInt(1)->compareTo(BigInt::fromInt(-1)));
        self::assertSame(-1, BigInt::fromInt(-1)->compareTo(BigInt::fromInt(1)));
        self::assertSame(0, BigInt::zero()->compareTo(BigInt::zero()));
    }

    public function test_compare_to_large(): void
    {
        $a = BigInt::fromString('123456789012345678901234567890');
        $b = BigInt::fromString('123456789012345678901234567891');

        self::assertSame(-1, $a->compareTo($b));
        self::assertSame(1, $b->compareTo($a));
        self::assertSame(0, $a->compareTo($a));
    }

    public function test_equals(): void
    {
        self::assertTrue(BigInt::fromInt(7)->equals(BigInt::fromInt(7)));
        self::assertFalse(BigInt::fromInt(7)->equals(BigInt::fromInt(-7)));
        self::assertTrue(BigInt::zero()->equals(BigInt::fromString('-0')));
    }

    public function test_signum(): void
    {
        self::assertSame(1, BigInt::fromInt(42)->signum());
        self::assertSame(-1, BigInt::fromInt(-42)->signum());
        self::assertSame(0, BigInt::zero()->signum());
    }

    public function test_to_int_within_range(): void
    {
        self::assertSame(42, BigInt::fromInt(42)->toInt());
        self::assertSame(-42, BigInt::fromInt(-42)->toInt());
        self::assertSame(PHP_INT_MAX, BigInt::fromInt(PHP_INT_MAX)->toInt());
        self::assertSame(PHP_INT_MIN, BigInt::fromInt(PHP_INT_MIN)->toInt());
    }

    public function test_to_int_overflow_throws(): void
    {
        $this->expectException(OverflowException::class);
        BigInt::fromString('99999999999999999999999999')->toInt();
    }

    public function test_to_int_underflow_throws(): void
    {
        $this->expectException(OverflowException::class);
        BigInt::fromString('-99999999999999999999999999')->toInt();
    }

    public function test_fits_in_php_int_boundaries(): void
    {
        self::assertTrue(BigInt::fromInt(PHP_INT_MAX)->fitsInPhpInt());
        self::assertTrue(BigInt::fromInt(PHP_INT_MIN)->fitsInPhpInt());
        self::assertFalse(BigInt::fromString((string) PHP_INT_MAX)->add(BigInt::one())->fitsInPhpInt());
        self::assertFalse(BigInt::fromString((string) PHP_INT_MIN)->subtract(BigInt::one())->fitsInPhpInt());
    }

    public function test_to_string_roundtrip_via_from_string(): void
    {
        $samples = [
            '0',
            '1',
            '-1',
            '999999999',
            '1000000000',
            '999999999999999999',
            '-123456789012345678901234567890',
            '999999999999999999999999999999',
        ];

        foreach ($samples as $sample) {
            self::assertSame($sample, (string) BigInt::fromString($sample));
        }
    }

    public function test_to_string_no_leading_zeros(): void
    {
        $value = BigInt::fromString('1000000000')->subtract(BigInt::fromString('1'));

        self::assertSame('999999999', (string) $value);
    }

    public function test_subtract_zero_yields_self(): void
    {
        self::assertSame('42', (string) BigInt::fromInt(42)->subtract(BigInt::zero()));
        self::assertSame('-42', (string) BigInt::zero()->subtract(BigInt::fromInt(42)));
    }
}
