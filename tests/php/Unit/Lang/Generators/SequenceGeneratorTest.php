<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use ArrayIterator;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Generators\SequenceGenerator;
use Phel\Lang\Ratio;
use PHPUnit\Framework\TestCase;
use Traversable;

use function count;

use const PHP_INT_MAX;

final class SequenceGeneratorTest extends TestCase
{
    public function test_to_iterable_with_array(): void
    {
        $result = SequenceGenerator::toIterable([1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_to_iterable_with_string(): void
    {
        $result = SequenceGenerator::toIterable('abc');

        self::assertSame(['a', 'b', 'c'], iterator_to_array($result, false));
    }

    public function test_to_iterable_with_multibyte_string(): void
    {
        $result = SequenceGenerator::toIterable('日本語');

        self::assertSame(['日', '本', '語'], iterator_to_array($result, false));
    }

    public function test_to_iterable_with_null(): void
    {
        $result = SequenceGenerator::toIterable(null);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_to_iterable_with_generator(): void
    {
        $generator = (static function (): Generator {
            yield 1;
            yield 2;
            yield 3;
        })();

        $result = SequenceGenerator::toIterable($generator);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_to_iterator_reuses_iterator_instances(): void
    {
        $iterator = new ArrayIterator([1, 2, 3]);

        $result = SequenceGenerator::toIterator($iterator);

        self::assertSame($iterator, $result);
        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_to_iterator_wraps_arrays(): void
    {
        $result = SequenceGenerator::toIterator(['a', 'b', 'c']);

        self::assertInstanceOf(ArrayIterator::class, $result);
        self::assertSame(['a', 'b', 'c'], iterator_to_array($result, false));
    }

    public function test_to_iterator_wraps_iterator_aggregate(): void
    {
        $aggregate = new class() implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                yield 'x';
                yield 'y';
            }
        };

        $result = SequenceGenerator::toIterator($aggregate);

        self::assertSame(['x', 'y'], iterator_to_array($result, false));
    }

    public function test_to_iterator_splits_multibyte_strings(): void
    {
        $result = SequenceGenerator::toIterator('🎉🎊');

        self::assertSame(['🎉', '🎊'], iterator_to_array($result, false));
    }

    public function test_indexed_pairs_values_with_zero_based_indexes(): void
    {
        $result = SequenceGenerator::indexed(['a', 'b', 'c']);

        self::assertSame([[0, 'a'], [1, 'b'], [2, 'c']], iterator_to_array($result, false));
    }

    public function test_indexed_splits_multibyte_strings(): void
    {
        $result = SequenceGenerator::indexed('🎉🎊');

        self::assertSame([[0, '🎉'], [1, '🎊']], iterator_to_array($result, false));
    }

    public function test_indexed_treats_null_as_empty(): void
    {
        $result = SequenceGenerator::indexed(null);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_range_basic(): void
    {
        $result = SequenceGenerator::range(0, 5, 1);

        self::assertSame([0, 1, 2, 3, 4], iterator_to_array($result, false));
    }

    public function test_range_with_step(): void
    {
        $result = SequenceGenerator::range(0, 10, 2);

        self::assertSame([0, 2, 4, 6, 8], iterator_to_array($result, false));
    }

    public function test_range_negative_step(): void
    {
        $result = SequenceGenerator::range(5, 0, -1);

        self::assertSame([5, 4, 3, 2, 1], iterator_to_array($result, false));
    }

    public function test_range_float(): void
    {
        $result = SequenceGenerator::range(0.0, 1.0, 0.25);

        self::assertSame([0.0, 0.25, 0.5, 0.75], iterator_to_array($result, false));
    }

    public function test_range_empty(): void
    {
        $result = SequenceGenerator::range(5, 0, 1);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_range_with_ratio_end(): void
    {
        $result = SequenceGenerator::range(0, Ratio::create(5, 2), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_with_ratio_step(): void
    {
        $result = iterator_to_array(SequenceGenerator::range(0, 2, Ratio::create(1, 2)), false);

        self::assertCount(4, $result);
        self::assertSame(0, $result[0]);
        self::assertTrue(Ratio::create(1, 2)->equals($result[1]));
        self::assertSame(1, $result[2]);
        self::assertTrue(Ratio::create(3, 2)->equals($result[3]));
    }

    public function test_range_with_negative_ratio_step(): void
    {
        $result = iterator_to_array(SequenceGenerator::range(1, -1, Ratio::create(-1, 2)), false);

        self::assertCount(4, $result);
    }

    public function test_range_with_big_int_end(): void
    {
        $result = SequenceGenerator::range(0, BigInt::fromInt(3), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_with_big_decimal_end(): void
    {
        $result = SequenceGenerator::range(0, BigDecimal::fromInt(3), 1);

        self::assertSame([0, 1, 2], iterator_to_array($result, false));
    }

    public function test_range_with_float_and_ratio_stays_float(): void
    {
        $result = SequenceGenerator::range(0.0, 1, Ratio::create(1, 2));

        self::assertSame([0.0, 0.5], iterator_to_array($result, false));
    }

    public function test_range_end_past_int_max_does_not_wrap(): void
    {
        $end = BigInt::fromInt(PHP_INT_MAX)->add(BigInt::fromInt(5));
        $result = SequenceGenerator::range(PHP_INT_MAX - 1, $end, 1);

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

        SequenceGenerator::range(0, 'x', 1);
    }
}
