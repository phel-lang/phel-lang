<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Phel\Lang\Generators\CombineGenerator;
use PHPUnit\Framework\TestCase;

final class CombineGeneratorTest extends TestCase
{
    // ==================== concat tests ====================

    public function test_concat_skips_null_arguments(): void
    {
        $result = CombineGenerator::concat([1, 2], null, [3, 4]);

        self::assertSame([1, 2, 3, 4], iterator_to_array($result, false));
    }

    public function test_concat_preserves_null_values_in_collections(): void
    {
        $result = CombineGenerator::concat([1, null, 2], [3]);

        self::assertSame([1, null, 2, 3], iterator_to_array($result, false));
    }

    // ==================== interpose tests ====================

    public function test_interpose_basic(): void
    {
        $result = CombineGenerator::interpose(',', [1, 2, 3]);

        self::assertSame([1, ',', 2, ',', 3], iterator_to_array($result, false));
    }

    public function test_interpose_single_element(): void
    {
        $result = CombineGenerator::interpose(',', [1]);

        self::assertSame([1], iterator_to_array($result, false));
    }

    public function test_interpose_empty(): void
    {
        $result = CombineGenerator::interpose(',', []);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_interpose_with_string(): void
    {
        $result = CombineGenerator::interpose('-', 'abc');

        self::assertSame(['a', '-', 'b', '-', 'c'], iterator_to_array($result, false));
    }

    // ==================== interleave tests ====================

    public function test_interleave_basic(): void
    {
        $result = CombineGenerator::interleave([1, 2, 3], ['a', 'b', 'c']);

        self::assertSame([1, 'a', 2, 'b', 3, 'c'], iterator_to_array($result, false));
    }

    public function test_interleave_different_lengths(): void
    {
        $result = CombineGenerator::interleave([1, 2, 3], ['a', 'b']);

        self::assertSame([1, 'a', 2, 'b'], iterator_to_array($result, false));
    }

    public function test_interleave_stops_on_nil_input(): void
    {
        $result = CombineGenerator::interleave([1, 2, 3], null);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_interleave_empty(): void
    {
        $result = CombineGenerator::interleave();

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_interleave_three_iterables(): void
    {
        $result = CombineGenerator::interleave([1, 2], ['a', 'b'], ['x', 'y']);

        self::assertSame([1, 'a', 'x', 2, 'b', 'y'], iterator_to_array($result, false));
    }

    // ==================== mapMulti tests ====================

    public function test_map_multi_basic(): void
    {
        $result = CombineGenerator::mapMulti(
            static fn($a, $b): int => $a + $b,
            [1, 2, 3],
            [10, 20, 30],
        );

        self::assertSame([11, 22, 33], iterator_to_array($result, false));
    }

    public function test_map_multi_stops_at_shortest(): void
    {
        $result = CombineGenerator::mapMulti(
            static fn($a, $b): int => $a + $b,
            [1, 2, 3, 4, 5],
            [10, 20],
        );

        self::assertSame([11, 22], iterator_to_array($result, false));
    }

    public function test_map_multi_empty_iterables(): void
    {
        $result = CombineGenerator::mapMulti(
            static fn($a, $b): int => $a + $b,
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_map_multi_three_iterables(): void
    {
        $result = CombineGenerator::mapMulti(
            static fn($a, $b, $c): int => $a + $b + $c,
            [1, 2],
            [10, 20],
            [100, 200],
        );

        self::assertSame([111, 222], iterator_to_array($result, false));
    }
}
