<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Phel\Lang\Generators\SequenceGenerator;
use PHPUnit\Framework\TestCase;

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
}
