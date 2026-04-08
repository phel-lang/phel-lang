<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Generator;
use Phel\Lang\Generators\SequenceGenerator;
use PHPUnit\Framework\TestCase;

final class SequenceGeneratorTest extends TestCase
{
    // ==================== toIterable tests ====================

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

    // ==================== range tests ====================

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
}
