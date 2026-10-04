<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Generator;
use InvalidArgumentException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Ratio;
use Phel\Lang\Seq;
use PHPUnit\Framework\TestCase;

use function count;

use const PHP_INT_MAX;

final class SeqTest extends TestCase
{
    public function test_first_of_an_array(): void
    {
        self::assertSame(1, Seq::first([1, 2, 3]));
    }

    public function test_first_of_an_empty_iterable_is_null(): void
    {
        self::assertNull(Seq::first([]));
    }

    public function test_first_keeps_a_falsy_element(): void
    {
        self::assertSame(0, Seq::first([0, 1]));
        self::assertFalse(Seq::first([false, 1]));
    }

    public function test_first_pulls_exactly_one_element(): void
    {
        $pulled = 0;
        $generator = (static function () use (&$pulled): Generator {
            foreach ([10, 20, 30] as $value) {
                ++$pulled;
                yield $value;
            }
        })();

        self::assertSame(10, Seq::first($generator));
        self::assertSame(1, $pulled, 'first must not drain the source');
    }

    public function test_first_of_an_infinite_generator_terminates(): void
    {
        $generator = (static function (): Generator {
            $i = 0;
            while (true) {
                yield $i++;
            }
        })();

        self::assertSame(0, Seq::first($generator));
    }

    public function test_range_with_ratio_end(): void
    {
        $result = Seq::range(0, Ratio::create(5, 2), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_coerces_leading_zero_numeric_strings_to_ints(): void
    {
        self::assertSame([2, 3], iterator_to_array(Seq::range('02', '04', '01'), false));
    }

    public function test_range_coerces_decimal_and_exponent_numeric_strings_to_floats(): void
    {
        self::assertSame([2.0, 3.0], iterator_to_array(Seq::range('2.0', 4, 1), false));
        self::assertSame([2.0, 3.0], iterator_to_array(Seq::range('2e0', 4, 1), false));
    }

    public function test_range_coerces_booleans_to_ints(): void
    {
        self::assertSame([0], iterator_to_array(Seq::range(false, true, true), false));
    }

    public function test_range_with_ratio_step(): void
    {
        $result = iterator_to_array(Seq::range(0, 2, Ratio::create(1, 2)), false);

        self::assertCount(4, $result);
        self::assertSame(0, $result[0]);
        self::assertTrue(Ratio::create(1, 2)->equals($result[1]));
        self::assertSame(1, $result[2]);
        self::assertTrue(Ratio::create(3, 2)->equals($result[3]));
    }

    public function test_range_with_negative_ratio_step(): void
    {
        $result = iterator_to_array(Seq::range(1, -1, Ratio::create(-1, 2)), false);

        self::assertCount(4, $result);
    }

    public function test_range_with_big_int_end(): void
    {
        $result = Seq::range(0, BigInt::fromInt(3), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_with_big_decimal_end(): void
    {
        $result = Seq::range(0, BigDecimal::fromInt(3), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_with_float_and_ratio_stays_float(): void
    {
        $result = Seq::range(0.0, 1, Ratio::create(1, 2));

        self::assertSame([0.0, 0.5], iterator_to_array($result, false));
    }

    public function test_range_end_past_int_max_does_not_wrap(): void
    {
        $end = BigInt::fromInt(PHP_INT_MAX)->add(BigInt::fromInt(5));
        $result = Seq::range(PHP_INT_MAX - 1, $end, 1);

        $values = [];
        foreach ($result as $value) {
            $values[] = $value;
            if (count($values) === 4) {
                break;
            }
        }

        self::assertSame(PHP_INT_MAX - 1, $values[0]);
        self::assertSame(PHP_INT_MAX, $values[1]);
        self::assertInstanceOf(BigInt::class, $values[2]);
        self::assertInstanceOf(BigInt::class, $values[3]);
    }

    public function test_range_rejects_non_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Seq::range(0, 'x', 1);
    }
}
