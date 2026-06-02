<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\IntegerOverflow;
use PHPUnit\Framework\TestCase;

final class IntegerOverflowTest extends TestCase
{
    public function test_on_add_is_false_for_small_operands(): void
    {
        self::assertFalse(IntegerOverflow::onAdd(2, 3));
        self::assertFalse(IntegerOverflow::onAdd(-2, -3));
        self::assertFalse(IntegerOverflow::onAdd(PHP_INT_MAX - 1, 1));
    }

    public function test_on_add_detects_positive_overflow(): void
    {
        self::assertTrue(IntegerOverflow::onAdd(PHP_INT_MAX, 1));
    }

    public function test_on_add_detects_negative_overflow(): void
    {
        self::assertTrue(IntegerOverflow::onAdd(PHP_INT_MIN, -1));
    }

    public function test_on_add_mixed_signs_never_overflow(): void
    {
        self::assertFalse(IntegerOverflow::onAdd(PHP_INT_MAX, -1));
        self::assertFalse(IntegerOverflow::onAdd(PHP_INT_MIN, 1));
    }

    public function test_on_subtract_is_false_for_safe_operands(): void
    {
        self::assertFalse(IntegerOverflow::onSubtract(5, 3));
        self::assertFalse(IntegerOverflow::onSubtract(PHP_INT_MAX, PHP_INT_MAX));
        self::assertFalse(IntegerOverflow::onSubtract(PHP_INT_MIN, PHP_INT_MIN));
    }

    public function test_on_subtract_detects_overflow_subtracting_negative(): void
    {
        self::assertTrue(IntegerOverflow::onSubtract(PHP_INT_MAX, -1));
    }

    public function test_on_subtract_detects_underflow_subtracting_positive(): void
    {
        self::assertTrue(IntegerOverflow::onSubtract(PHP_INT_MIN, 1));
    }

    public function test_on_multiply_is_false_for_zero_and_one(): void
    {
        self::assertFalse(IntegerOverflow::onMultiply(0, PHP_INT_MAX));
        self::assertFalse(IntegerOverflow::onMultiply(PHP_INT_MAX, 1));
        self::assertFalse(IntegerOverflow::onMultiply(1, PHP_INT_MIN));
    }

    public function test_on_multiply_php_int_min_overflows_for_any_other_factor(): void
    {
        self::assertTrue(IntegerOverflow::onMultiply(PHP_INT_MIN, 2));
        self::assertTrue(IntegerOverflow::onMultiply(2, PHP_INT_MIN));
        self::assertTrue(IntegerOverflow::onMultiply(PHP_INT_MIN, -1));
    }

    public function test_on_multiply_detects_large_product_overflow(): void
    {
        self::assertTrue(IntegerOverflow::onMultiply(PHP_INT_MAX, 2));
        self::assertTrue(IntegerOverflow::onMultiply(1_000_000_000, 1_000_000_000_000));
    }

    public function test_on_multiply_is_false_for_safe_product(): void
    {
        self::assertFalse(IntegerOverflow::onMultiply(1000, 1000));
        self::assertFalse(IntegerOverflow::onMultiply(-7, 8));
    }
}
