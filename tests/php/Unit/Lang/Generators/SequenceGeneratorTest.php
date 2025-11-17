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
}
