<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use ArithmeticError;
use InvalidArgumentException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInteger;
use PHPUnit\Framework\TestCase;

final class BigDecimalTest extends TestCase
{
    public function test_from_string_integer(): void
    {
        self::assertSame('123M', (string) BigDecimal::fromString('123'));
    }

    public function test_from_string_decimal(): void
    {
        self::assertSame('1.5M', (string) BigDecimal::fromString('1.5'));
        self::assertSame('-1.5M', (string) BigDecimal::fromString('-1.5'));
    }

    public function test_from_string_strips_leading_zeros(): void
    {
        self::assertSame('1.5M', (string) BigDecimal::fromString('001.5'));
    }

    public function test_from_string_handles_scientific_notation(): void
    {
        self::assertSame('15M', (string) BigDecimal::fromString('1.5e1'));
        self::assertSame('0.015M', (string) BigDecimal::fromString('1.5e-2'));
    }

    public function test_from_string_underscores_allowed(): void
    {
        self::assertSame('1000.25M', (string) BigDecimal::fromString('1_000.25'));
    }

    public function test_from_string_rejects_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigDecimal::fromString('not-a-number');
    }

    public function test_from_int(): void
    {
        self::assertSame('42M', (string) BigDecimal::fromInt(42));
        self::assertSame('-7M', (string) BigDecimal::fromInt(-7));
    }

    public function test_from_big_integer(): void
    {
        $bi = BigInteger::fromString('100000000000000000000');

        self::assertSame('100000000000000000000M', (string) BigDecimal::fromBigInteger($bi));
    }

    public function test_from_float_uses_shortest_round_trip(): void
    {
        self::assertSame('0.1M', (string) BigDecimal::fromFloat(0.1));
        self::assertSame('1.5M', (string) BigDecimal::fromFloat(1.5));
    }

    public function test_from_float_rejects_nan_and_inf(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BigDecimal::fromFloat(NAN);
    }

    public function test_add_aligns_scales(): void
    {
        $r = BigDecimal::fromString('0.1')->add(BigDecimal::fromString('0.2'));

        self::assertSame('0.3M', (string) $r);
    }

    public function test_subtract_aligns_scales(): void
    {
        $r = BigDecimal::fromString('1.5')->subtract(BigDecimal::fromString('0.25'));

        self::assertSame('1.25M', (string) $r);
    }

    public function test_multiply_combines_scales(): void
    {
        $r = BigDecimal::fromString('1.5')->multiply(BigDecimal::fromString('2.0'));

        self::assertSame('3M', (string) $r);
    }

    public function test_divide_exact_terminating(): void
    {
        $r = BigDecimal::fromString('1.0')->divideExact(BigDecimal::fromString('4'));

        self::assertSame('0.25M', (string) $r);
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
        self::assertSame('-1.5M', (string) BigDecimal::fromString('1.5')->negate());
        self::assertSame('1.5M', (string) BigDecimal::fromString('-1.5')->negate());
    }

    public function test_abs(): void
    {
        self::assertSame('1.5M', (string) BigDecimal::fromString('-1.5')->abs());
        self::assertSame('1.5M', (string) BigDecimal::fromString('1.5')->abs());
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
}
