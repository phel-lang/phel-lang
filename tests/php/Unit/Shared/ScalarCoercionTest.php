<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\ScalarCoercion;
use PHPUnit\Framework\TestCase;

final class ScalarCoercionTest extends TestCase
{
    public function test_to_string_passes_scalars_through(): void
    {
        self::assertSame('foo', ScalarCoercion::toString('foo'));
        self::assertSame('42', ScalarCoercion::toString(42));
        self::assertSame('1', ScalarCoercion::toString(true));
        self::assertSame('1.5', ScalarCoercion::toString(1.5));
    }

    public function test_to_string_returns_default_for_non_scalars(): void
    {
        self::assertSame('', ScalarCoercion::toString(null));
        self::assertSame('', ScalarCoercion::toString(['a']));
        self::assertSame('fallback', ScalarCoercion::toString(null, 'fallback'));
    }

    public function test_to_int_coerces_numeric_values(): void
    {
        self::assertSame(8080, ScalarCoercion::toInt(8080));
        self::assertSame(8080, ScalarCoercion::toInt('8080'));
        self::assertSame(3, ScalarCoercion::toInt(3.9));
    }

    public function test_to_int_returns_default_for_non_numeric(): void
    {
        self::assertSame(0, ScalarCoercion::toInt(null));
        self::assertSame(0, ScalarCoercion::toInt('abc'));
        self::assertSame(7, ScalarCoercion::toInt([], 7));
    }

    public function test_to_float_coerces_numeric_values(): void
    {
        self::assertSame(1.5, ScalarCoercion::toFloat(1.5));
        self::assertSame(2.0, ScalarCoercion::toFloat('2'));
    }

    public function test_to_float_returns_default_for_non_numeric(): void
    {
        self::assertSame(0.0, ScalarCoercion::toFloat(null));
        self::assertSame(9.5, ScalarCoercion::toFloat('x', 9.5));
    }
}
