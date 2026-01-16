<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Generator;
use Phel\Lang\Generators\SequenceGenerator;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class SequenceGeneratorTest extends TestCase
{
    public function test_mapcat_skips_null_results(): void
    {
        $result = SequenceGenerator::mapcat(
            static fn ($x): ?array => $x > 0 ? [$x, $x] : null,
            [1, -1, 2, -2, 3],
        );

        self::assertSame([1, 1, 2, 2, 3, 3], iterator_to_array($result, false));
    }

    public function test_mapcat_preserves_null_values_in_collections(): void
    {
        $result = SequenceGenerator::mapcat(
            static fn ($x): array => [$x, null],
            [1, 2, 3],
        );

        self::assertSame([1, null, 2, null, 3, null], iterator_to_array($result, false));
    }

    public function test_mapcat_with_string_iterable(): void
    {
        $result = SequenceGenerator::mapcat(
            static fn ($char): ?array => $char === 'a' ? [$char, $char] : null,
            'abac',
        );

        self::assertSame(['a', 'a', 'a', 'a'], iterator_to_array($result, false));
    }

    public function test_mapcat_all_null_results_returns_empty(): void
    {
        $result = SequenceGenerator::mapcat(
            static fn ($x): null => null,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_concat_skips_null_arguments(): void
    {
        $result = SequenceGenerator::concat([1, 2], null, [3, 4]);

        self::assertSame([1, 2, 3, 4], iterator_to_array($result, false));
    }

    public function test_concat_preserves_null_values_in_collections(): void
    {
        $result = SequenceGenerator::concat([1, null, 2], [3]);

        self::assertSame([1, null, 2, 3], iterator_to_array($result, false));
    }

    public function test_compact_removes_null_by_default(): void
    {
        $result = SequenceGenerator::compact([1, null, 2, null, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_compact_removes_multiple_specified_values(): void
    {
        $result = SequenceGenerator::compact([1, null, 2, false, 3, 0], null, false, 0);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_compact_with_empty_collection(): void
    {
        $result = SequenceGenerator::compact([null, null, null]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_compact_preserves_non_matching_values(): void
    {
        $result = SequenceGenerator::compact([1, false, 2, null, 3], null);

        self::assertSame([1, false, 2, 3], iterator_to_array($result, false));
    }

    public function test_compact_strict_type_comparison_null_vs_empty_string(): void
    {
        // null and '' should be treated as distinct values
        $result = SequenceGenerator::compact([null, '', 'data', null], null);

        self::assertSame(['', 'data'], iterator_to_array($result, false));
    }

    public function test_compact_strict_type_comparison_zero_int_vs_string(): void
    {
        // 0 (int) and '0' (string) should be treated as distinct
        $result = SequenceGenerator::compact([0, '0', 1, '1'], 0);

        self::assertSame(['0', 1, '1'], iterator_to_array($result, false));
    }

    public function test_compact_strict_type_comparison_false_vs_zero(): void
    {
        // false and 0 should be treated as distinct
        $result = SequenceGenerator::compact([false, 0, true, 1], false);

        self::assertSame([0, true, 1], iterator_to_array($result, false));
    }

    public function test_compact_strict_type_comparison_true_vs_one(): void
    {
        // true and 1 should be treated as distinct
        $result = SequenceGenerator::compact([true, 1, false, 0], true);

        self::assertSame([1, false, 0], iterator_to_array($result, false));
    }

    public function test_compact_preserves_all_falsy_types_when_removing_only_null(): void
    {
        // When removing only null, all other falsy values should be preserved
        $result = SequenceGenerator::compact([null, '', 0, false, '0', [], null], null);

        self::assertSame(['', 0, false, '0', []], iterator_to_array($result, false));
    }

    public function test_compact_handles_objects(): void
    {
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 3];

        $result = SequenceGenerator::compact([$obj1, $obj2, $obj3], $obj2);

        $resultArray = iterator_to_array($result, false);
        self::assertCount(2, $resultArray);
        self::assertSame($obj1, $resultArray[0]);
        self::assertSame($obj3, $resultArray[1]);
    }

    public function test_compact_single_value_optimization_with_objects(): void
    {
        // Test single-value path with object
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 3];

        $result = SequenceGenerator::compact([$obj1, $obj2, $obj3], $obj2);

        $resultArray = iterator_to_array($result, false);
        self::assertCount(2, $resultArray);
        self::assertSame($obj1, $resultArray[0]);
        self::assertSame($obj3, $resultArray[1]);
    }

    public function test_compact_multiple_values_with_mixed_scalars_and_objects(): void
    {
        // Test hash lookup path with mixed types
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];

        $result = SequenceGenerator::compact(
            [1, $obj1, null, 2, $obj2, false, 3],
            null,
            false,
            $obj1,
        );

        $resultArray = iterator_to_array($result, false);
        self::assertSame([1, 2, $obj2, 3], $resultArray);
    }

    public function test_compact_multiple_objects_removal(): void
    {
        // Test multiple object removals
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 3];
        $obj4 = (object)['id' => 4];

        $result = SequenceGenerator::compact(
            [$obj1, $obj2, $obj3, $obj4],
            $obj2,
            $obj4,
        );

        $resultArray = iterator_to_array($result, false);
        self::assertSame([$obj1, $obj3], $resultArray);
    }

    public function test_compact_arrays_as_values(): void
    {
        // Test that arrays are correctly handled as scalars
        $arr1 = [1, 2];
        $arr2 = [3, 4];
        $arr3 = [5, 6];

        $result = SequenceGenerator::compact(
            [$arr1, $arr2, $arr3],
            $arr2,
        );

        $resultArray = iterator_to_array($result, false);
        self::assertCount(2, $resultArray);
        self::assertSame($arr1, $resultArray[0]);
        self::assertSame($arr3, $resultArray[1]);
    }

    public function test_compact_negative_zero_vs_positive_zero(): void
    {
        // PHP treats -0.0 and 0.0 as equal with ===, but var_export shows the difference
        // This test verifies our implementation handles edge cases correctly
        $result = SequenceGenerator::compact([0, 0.0, -0.0, 1], 0);

        // 0 (int) should be removed, but 0.0 and -0.0 (floats) should remain
        $resultArray = iterator_to_array($result, false);
        self::assertSame([0.0, -0.0, 1], $resultArray);
    }

    public function test_compact_empty_array_vs_false_vs_zero(): void
    {
        // Test distinction between empty array, false, and 0
        $result = SequenceGenerator::compact(
            [[], false, 0, '', null, 1],
            [],
        );

        $resultArray = iterator_to_array($result, false);
        self::assertSame([false, 0, '', null, 1], $resultArray);
    }

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

    // ==================== map tests ====================

    public function test_map_basic(): void
    {
        $result = SequenceGenerator::map(
            static fn ($x): int => $x * 2,
            [1, 2, 3],
        );

        self::assertSame([2, 4, 6], iterator_to_array($result, false));
    }

    public function test_map_empty_iterable(): void
    {
        $result = SequenceGenerator::map(
            static fn ($x): int => $x * 2,
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_map_with_string(): void
    {
        $result = SequenceGenerator::map(
            static fn ($char): string => strtoupper((string) $char),
            'abc',
        );

        self::assertSame(['A', 'B', 'C'], iterator_to_array($result, false));
    }

    public function test_map_is_lazy(): void
    {
        $callCount = 0;
        $generator = SequenceGenerator::map(
            static function ($x) use (&$callCount): int {
                ++$callCount;
                return $x * 2;
            },
            [1, 2, 3, 4, 5],
        );

        self::assertSame(0, $callCount);

        $generator->current();
        self::assertSame(1, $callCount);
    }

    // ==================== mapIndexed tests ====================

    public function test_map_indexed_basic(): void
    {
        $result = SequenceGenerator::mapIndexed(
            static fn (int $idx, $val): string => sprintf('%d:%s', $idx, $val),
            ['a', 'b', 'c'],
        );

        self::assertSame(['0:a', '1:b', '2:c'], iterator_to_array($result, false));
    }

    public function test_map_indexed_empty(): void
    {
        $result = SequenceGenerator::mapIndexed(
            static fn (int $idx, $val): string => sprintf('%d:%s', $idx, $val),
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    // ==================== mapMulti tests ====================

    public function test_map_multi_basic(): void
    {
        $result = SequenceGenerator::mapMulti(
            static fn ($a, $b): int => $a + $b,
            [1, 2, 3],
            [10, 20, 30],
        );

        self::assertSame([11, 22, 33], iterator_to_array($result, false));
    }

    public function test_map_multi_stops_at_shortest(): void
    {
        $result = SequenceGenerator::mapMulti(
            static fn ($a, $b): int => $a + $b,
            [1, 2, 3, 4, 5],
            [10, 20],
        );

        self::assertSame([11, 22], iterator_to_array($result, false));
    }

    public function test_map_multi_empty_iterables(): void
    {
        $result = SequenceGenerator::mapMulti(
            static fn ($a, $b): int => $a + $b,
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_map_multi_three_iterables(): void
    {
        $result = SequenceGenerator::mapMulti(
            static fn ($a, $b, $c): int => $a + $b + $c,
            [1, 2],
            [10, 20],
            [100, 200],
        );

        self::assertSame([111, 222], iterator_to_array($result, false));
    }

    // ==================== filter tests ====================

    public function test_filter_basic(): void
    {
        $result = SequenceGenerator::filter(
            static fn ($x): bool => $x > 2,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_filter_none_match(): void
    {
        $result = SequenceGenerator::filter(
            static fn ($x): bool => $x > 10,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_filter_all_match(): void
    {
        $result = SequenceGenerator::filter(
            static fn ($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_filter_with_string(): void
    {
        $result = SequenceGenerator::filter(
            static fn ($char): bool => $char !== 'b',
            'abc',
        );

        self::assertSame(['a', 'c'], iterator_to_array($result, false));
    }

    // ==================== keep tests ====================

    public function test_keep_basic(): void
    {
        $result = SequenceGenerator::keep(
            static fn ($x): ?int => $x > 2 ? $x * 10 : null,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([30, 40, 50], iterator_to_array($result, false));
    }

    public function test_keep_all_null(): void
    {
        $result = SequenceGenerator::keep(
            static fn ($x): null => null,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_keep_preserves_falsy_non_null(): void
    {
        $result = SequenceGenerator::keep(
            static fn ($x): mixed => $x % 2 === 0 ? 0 : null,
            [1, 2, 3, 4],
        );

        self::assertSame([0, 0], iterator_to_array($result, false));
    }

    // ==================== keepIndexed tests ====================

    public function test_keep_indexed_basic(): void
    {
        $result = SequenceGenerator::keepIndexed(
            static fn (int $idx, $val): ?string => $idx % 2 === 0 ? $val : null,
            ['a', 'b', 'c', 'd', 'e'],
        );

        self::assertSame(['a', 'c', 'e'], iterator_to_array($result, false));
    }

    public function test_keep_indexed_empty(): void
    {
        $result = SequenceGenerator::keepIndexed(
            static fn (int $idx, $val): ?string => $val,
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    // ==================== take tests ====================

    public function test_take_basic(): void
    {
        $result = SequenceGenerator::take(3, [1, 2, 3, 4, 5]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_more_than_available(): void
    {
        $result = SequenceGenerator::take(10, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_zero(): void
    {
        $result = SequenceGenerator::take(0, [1, 2, 3]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_take_is_lazy(): void
    {
        $callCount = 0;
        $generator = (static function () use (&$callCount): Generator {
            foreach ([1, 2, 3, 4, 5] as $item) {
                ++$callCount;
                yield $item;
            }
        })();

        $result = SequenceGenerator::take(2, $generator);
        self::assertSame([1, 2], iterator_to_array($result, false));
        // Generator may advance one extra during the break check, but should not process all 5
        self::assertLessThanOrEqual(3, $callCount);
    }

    // ==================== takeWhile tests ====================

    public function test_take_while_basic(): void
    {
        $result = SequenceGenerator::takeWhile(
            static fn ($x): bool => $x < 4,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_while_none_match(): void
    {
        $result = SequenceGenerator::takeWhile(
            static fn ($x): bool => $x < 0,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_take_while_all_match(): void
    {
        $result = SequenceGenerator::takeWhile(
            static fn ($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    // ==================== takeNth tests ====================

    public function test_take_nth_basic(): void
    {
        $result = SequenceGenerator::takeNth(2, [1, 2, 3, 4, 5, 6]);

        self::assertSame([1, 3, 5], iterator_to_array($result, false));
    }

    public function test_take_nth_every_third(): void
    {
        $result = SequenceGenerator::takeNth(3, [1, 2, 3, 4, 5, 6, 7, 8, 9]);

        self::assertSame([1, 4, 7], iterator_to_array($result, false));
    }

    public function test_take_nth_one(): void
    {
        $result = SequenceGenerator::takeNth(1, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    // ==================== drop tests ====================

    public function test_drop_basic(): void
    {
        $result = SequenceGenerator::drop(2, [1, 2, 3, 4, 5]);

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_drop_more_than_available(): void
    {
        $result = SequenceGenerator::drop(10, [1, 2, 3]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_drop_zero(): void
    {
        $result = SequenceGenerator::drop(0, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    // ==================== dropWhile tests ====================

    public function test_drop_while_basic(): void
    {
        $result = SequenceGenerator::dropWhile(
            static fn ($x): bool => $x < 3,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_drop_while_none_match(): void
    {
        $result = SequenceGenerator::dropWhile(
            static fn ($x): bool => $x < 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_drop_while_all_match(): void
    {
        $result = SequenceGenerator::dropWhile(
            static fn ($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    // ==================== interpose tests ====================

    public function test_interpose_basic(): void
    {
        $result = SequenceGenerator::interpose(',', [1, 2, 3]);

        self::assertSame([1, ',', 2, ',', 3], iterator_to_array($result, false));
    }

    public function test_interpose_single_element(): void
    {
        $result = SequenceGenerator::interpose(',', [1]);

        self::assertSame([1], iterator_to_array($result, false));
    }

    public function test_interpose_empty(): void
    {
        $result = SequenceGenerator::interpose(',', []);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_interpose_with_string(): void
    {
        $result = SequenceGenerator::interpose('-', 'abc');

        self::assertSame(['a', '-', 'b', '-', 'c'], iterator_to_array($result, false));
    }

    // ==================== interleave tests ====================

    public function test_interleave_basic(): void
    {
        $result = SequenceGenerator::interleave([1, 2, 3], ['a', 'b', 'c']);

        self::assertSame([1, 'a', 2, 'b', 3, 'c'], iterator_to_array($result, false));
    }

    public function test_interleave_different_lengths(): void
    {
        $result = SequenceGenerator::interleave([1, 2, 3], ['a', 'b']);

        self::assertSame([1, 'a', 2, 'b', 3, null], iterator_to_array($result, false));
    }

    public function test_interleave_empty(): void
    {
        $result = SequenceGenerator::interleave();

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_interleave_three_iterables(): void
    {
        $result = SequenceGenerator::interleave([1, 2], ['a', 'b'], ['x', 'y']);

        self::assertSame([1, 'a', 'x', 2, 'b', 'y'], iterator_to_array($result, false));
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

    // ==================== distinct tests ====================

    public function test_distinct_basic(): void
    {
        $result = SequenceGenerator::distinct([1, 2, 1, 3, 2, 4]);

        self::assertSame([1, 2, 3, 4], iterator_to_array($result, false));
    }

    public function test_distinct_preserves_order(): void
    {
        $result = SequenceGenerator::distinct([3, 1, 2, 1, 3, 2]);

        self::assertSame([3, 1, 2], iterator_to_array($result, false));
    }

    public function test_distinct_empty(): void
    {
        $result = SequenceGenerator::distinct([]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_distinct_with_string(): void
    {
        $result = SequenceGenerator::distinct('abracadabra');

        self::assertSame(['a', 'b', 'r', 'c', 'd'], iterator_to_array($result, false));
    }

    public function test_distinct_with_objects(): void
    {
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 1]; // Same content but different object

        $result = SequenceGenerator::distinct([$obj1, $obj2, $obj1, $obj3]);

        $resultArray = iterator_to_array($result, false);
        // $obj1 and $obj3 may or may not be considered equal depending on Equalizer implementation
        self::assertContains($obj1, $resultArray);
        self::assertContains($obj2, $resultArray);
    }

    // ==================== dedupe tests ====================

    public function test_dedupe_basic(): void
    {
        $result = SequenceGenerator::dedupe([1, 1, 2, 2, 2, 3, 1, 1]);

        self::assertSame([1, 2, 3, 1], iterator_to_array($result, false));
    }

    public function test_dedupe_no_consecutive_duplicates(): void
    {
        $result = SequenceGenerator::dedupe([1, 2, 3, 4]);

        self::assertSame([1, 2, 3, 4], iterator_to_array($result, false));
    }

    public function test_dedupe_all_same(): void
    {
        $result = SequenceGenerator::dedupe([1, 1, 1, 1]);

        self::assertSame([1], iterator_to_array($result, false));
    }

    public function test_dedupe_empty(): void
    {
        $result = SequenceGenerator::dedupe([]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_dedupe_with_string(): void
    {
        $result = SequenceGenerator::dedupe('aabbcc');

        self::assertSame(['a', 'b', 'c'], iterator_to_array($result, false));
    }
}
