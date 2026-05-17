<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use ArithmeticError;
use InvalidArgumentException;
use OverflowException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use PHPUnit\Framework\TestCase;

final class BigDecimalTest extends TestCase
{
    public function test_from_string_integer(): void
    {
        self::assertSame('123', (string) BigDecimal::fromString('123'));
    }

    public function test_from_string_decimal(): void
    {
        self::assertSame('1.5', (string) BigDecimal::fromString('1.5'));
        self::assertSame('-1.5', (string) BigDecimal::fromString('-1.5'));
    }

    public function test_from_string_strips_leading_zeros(): void
    {
        self::assertSame('1.5', (string) BigDecimal::fromString('001.5'));
    }

    public function test_from_string_handles_scientific_notation(): void
    {
        self::assertSame('15', (string) BigDecimal::fromString('1.5e1'));
        self::assertSame('0.015', (string) BigDecimal::fromString('1.5e-2'));
    }

    public function test_from_string_underscores_allowed(): void
    {
        self::assertSame('1000.25', (string) BigDecimal::fromString('1_000.25'));
    }

    public function test_from_string_rejects_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigDecimal::fromString('not-a-number');
    }

    public function test_from_int(): void
    {
        self::assertSame('42', (string) BigDecimal::fromInt(42));
        self::assertSame('-7', (string) BigDecimal::fromInt(-7));
    }

    public function test_from_big_integer(): void
    {
        $bi = BigInt::fromString('100000000000000000000');

        self::assertSame('100000000000000000000', (string) BigDecimal::fromBigInt($bi));
    }

    public function test_from_float_uses_shortest_round_trip(): void
    {
        self::assertSame('0.1', (string) BigDecimal::fromFloat(0.1));
        self::assertSame('1.5', (string) BigDecimal::fromFloat(1.5));
    }

    public function test_from_float_rejects_nan_and_inf(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigDecimal::fromFloat(NAN);
    }

    public function test_add_aligns_scales(): void
    {
        $r = BigDecimal::fromString('0.1')->add(BigDecimal::fromString('0.2'));

        self::assertSame('0.3', (string) $r);
    }

    public function test_subtract_aligns_scales(): void
    {
        $r = BigDecimal::fromString('1.5')->subtract(BigDecimal::fromString('0.25'));

        self::assertSame('1.25', (string) $r);
    }

    public function test_multiply_combines_scales(): void
    {
        $r = BigDecimal::fromString('1.5')->multiply(BigDecimal::fromString('2.0'));

        self::assertSame('3.00', (string) $r);
    }

    public function test_divide_exact_terminating(): void
    {
        $r = BigDecimal::fromString('1.0')->divideExact(BigDecimal::fromString('4'));

        self::assertSame('0.25', (string) $r);
    }

    public function test_divide_exact_non_terminating_throws(): void
    {
        $this->expectException(ArithmeticError::class);
        BigDecimal::fromString('1')->divideExact(BigDecimal::fromString('3'));
    }

    public function test_divide_by_zero_throws(): void
    {
        $this->expectException(ArithmeticError::class);
        BigDecimal::fromString('1')->divideExact(BigDecimal::fromString('0'));
    }

    public function test_negate(): void
    {
        self::assertSame('-1.5', (string) BigDecimal::fromString('1.5')->negate());
        self::assertSame('1.5', (string) BigDecimal::fromString('-1.5')->negate());
    }

    public function test_abs(): void
    {
        self::assertSame('1.5', (string) BigDecimal::fromString('-1.5')->abs());
        self::assertSame('1.5', (string) BigDecimal::fromString('1.5')->abs());
    }

    public function test_compare_to_aligns_scales(): void
    {
        self::assertSame(0, BigDecimal::fromString('1.20')->compareTo(BigDecimal::fromString('1.2')));
        self::assertSame(-1, BigDecimal::fromString('1.2')->compareTo(BigDecimal::fromString('1.5')));
        self::assertSame(1, BigDecimal::fromString('2.0')->compareTo(BigDecimal::fromString('1.5')));
    }

    public function test_equals_after_scale_normalisation(): void
    {
        $a = BigDecimal::fromString('1.20');
        $b = BigDecimal::fromString('1.2');

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_to_plain_string_keeps_trailing_zeros(): void
    {
        self::assertSame('1.20', BigDecimal::fromString('1.20')->toPlainString());
    }

    public function test_signum_reports_sign(): void
    {
        self::assertSame(0, BigDecimal::fromString('0')->signum());
        self::assertSame(0, BigDecimal::fromString('0.000')->signum());
        self::assertSame(1, BigDecimal::fromString('0.0001')->signum());
        self::assertSame(-1, BigDecimal::fromString('-0.0001')->signum());
        self::assertSame(1, BigDecimal::fromString('42')->signum());
        self::assertSame(-1, BigDecimal::fromString('-42.5')->signum());
    }

    public function test_to_int_truncates_toward_zero(): void
    {
        self::assertSame(0, BigDecimal::fromString('0')->toInt());
        self::assertSame(1, BigDecimal::fromString('1')->toInt());
        self::assertSame(-1, BigDecimal::fromString('-1')->toInt());
        self::assertSame(2, BigDecimal::fromString('2.7')->toInt());
        self::assertSame(-1, BigDecimal::fromString('-1.9')->toInt());
        self::assertSame(0, BigDecimal::fromString('-0.5')->toInt());
    }

    public function test_to_int_throws_when_out_of_range(): void
    {
        $this->expectException(OverflowException::class);
        BigDecimal::fromString('99999999999999999999999')->toInt();
    }

    public function test_to_float_returns_native_double(): void
    {
        self::assertSame(0.0, BigDecimal::fromString('0')->toFloat());
        self::assertSame(1.5, BigDecimal::fromString('1.5')->toFloat());
        self::assertSame(-1.1, BigDecimal::fromString('-1.1')->toFloat());
        self::assertSame(1500.0, BigDecimal::fromString('1.5e3')->toFloat());
    }
}
