<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\NumericCoercion;
use Phel\Lang\Ratio;
use PHPUnit\Framework\TestCase;

final class NumericCoercionTest extends TestCase
{
    public function test_ensure_numeric_accepts_every_numeric_type(): void
    {
        NumericCoercion::ensureNumeric(1);
        NumericCoercion::ensureNumeric(1.5);
        NumericCoercion::ensureNumeric(BigInt::fromInt(1));
        NumericCoercion::ensureNumeric(Ratio::create(1, 2));
        NumericCoercion::ensureNumeric(BigDecimal::fromString('1.5'));

        $this->expectNotToPerformAssertions();
    }

    public function test_ensure_numeric_rejects_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericCoercion::ensureNumeric('1');
    }

    public function test_to_float(): void
    {
        self::assertSame(2.0, NumericCoercion::toFloat(2));
        self::assertSame(2.5, NumericCoercion::toFloat(2.5));
        self::assertSame(3.0, NumericCoercion::toFloat(BigInt::fromInt(3)));
        self::assertSame(0.5, NumericCoercion::toFloat(Ratio::create(1, 2)));
        self::assertSame(1.5, NumericCoercion::toFloat(BigDecimal::fromString('1.5')));
    }

    public function test_to_big_int_from_int_and_big_int(): void
    {
        self::assertSame('5', (string) NumericCoercion::toBigInt(5));
        $big = BigInt::fromInt(7);
        self::assertSame($big, NumericCoercion::toBigInt($big));
    }

    public function test_to_big_int_rejects_float(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericCoercion::toBigInt(1.5);
    }

    public function test_to_big_decimal_from_each_numeric_type(): void
    {
        self::assertSame(0, NumericCoercion::toBigDecimal(2)->compareTo(BigDecimal::fromInt(2)));
        self::assertSame(0, NumericCoercion::toBigDecimal(BigInt::fromInt(2))->compareTo(BigDecimal::fromInt(2)));
        self::assertSame(0, NumericCoercion::toBigDecimal(Ratio::create(1, 2))->compareTo(BigDecimal::fromString('0.5')));
    }

    public function test_rational_operand_passes_through_int_big_int_ratio(): void
    {
        self::assertSame(3, NumericCoercion::rationalOperand(3));
        $big = BigInt::fromInt(4);
        self::assertSame($big, NumericCoercion::rationalOperand($big));
        $ratio = Ratio::create(1, 2);
        self::assertSame($ratio, NumericCoercion::rationalOperand($ratio));
    }

    public function test_rational_operand_rejects_float(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericCoercion::rationalOperand(1.5);
    }

    public function test_collapse_big_int_returns_native_int_when_it_fits(): void
    {
        self::assertSame(5, NumericCoercion::collapseBigInt(BigInt::fromInt(5)));
        self::assertSame(PHP_INT_MAX, NumericCoercion::collapseBigInt(BigInt::fromInt(PHP_INT_MAX)));
    }

    public function test_collapse_big_int_keeps_big_int_when_out_of_range(): void
    {
        $huge = BigInt::fromString('99999999999999999999999999');
        self::assertSame($huge, NumericCoercion::collapseBigInt($huge));
    }

    public function test_to_int_exponent(): void
    {
        self::assertSame(4, NumericCoercion::toIntExponent(4));
        self::assertSame(4, NumericCoercion::toIntExponent(BigInt::fromInt(4)));
    }

    public function test_to_int_exponent_rejects_out_of_range_big_int(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericCoercion::toIntExponent(BigInt::fromString('99999999999999999999999999'));
    }

    public function test_to_int_exponent_rejects_float(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumericCoercion::toIntExponent(1.5);
    }

    public function test_truncate_to_int(): void
    {
        self::assertSame(3, NumericCoercion::truncateToInt(3));
        self::assertSame(3, NumericCoercion::truncateToInt(Ratio::create(7, 2)));
        self::assertSame(-3, NumericCoercion::truncateToInt(Ratio::create(-7, 2)));
        $big = BigInt::fromInt(9);
        self::assertSame($big, NumericCoercion::truncateToInt($big));
    }
}
