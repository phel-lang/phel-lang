<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Phel\Lang\Generators\TransformGenerator;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class TransformGeneratorTest extends TestCase
{
    public function test_mapcat_skips_null_results(): void
    {
        $result = TransformGenerator::mapcat(
            static fn($x): ?array => $x > 0 ? [$x, $x] : null,
            [1, -1, 2, -2, 3],
        );

        self::assertSame([1, 1, 2, 2, 3, 3], iterator_to_array($result, false));
    }

    public function test_mapcat_preserves_null_values_in_collections(): void
    {
        $result = TransformGenerator::mapcat(
            static fn($x): array => [$x, null],
            [1, 2, 3],
        );

        self::assertSame([1, null, 2, null, 3, null], iterator_to_array($result, false));
    }

    public function test_mapcat_with_string_iterable(): void
    {
        $result = TransformGenerator::mapcat(
            static fn($char): ?array => $char === 'a' ? [$char, $char] : null,
            'abac',
        );

        self::assertSame(['a', 'a', 'a', 'a'], iterator_to_array($result, false));
    }

    public function test_mapcat_all_null_results_returns_empty(): void
    {
        $result = TransformGenerator::mapcat(
            static fn($x): null => null,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    // ==================== map tests ====================

    public function test_map_basic(): void
    {
        $result = TransformGenerator::map(
            static fn($x): int => $x * 2,
            [1, 2, 3],
        );

        self::assertSame([2, 4, 6], iterator_to_array($result, false));
    }

    public function test_map_empty_iterable(): void
    {
        $result = TransformGenerator::map(
            static fn($x): int => $x * 2,
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_map_with_string(): void
    {
        $result = TransformGenerator::map(
            static fn($char): string => strtoupper((string) $char),
            'abc',
        );

        self::assertSame(['A', 'B', 'C'], iterator_to_array($result, false));
    }

    public function test_map_is_lazy(): void
    {
        $callCount = 0;
        $generator = TransformGenerator::map(
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
        $result = TransformGenerator::mapIndexed(
            static fn(int $idx, $val): string => sprintf('%d:%s', $idx, $val),
            ['a', 'b', 'c'],
        );

        self::assertSame(['0:a', '1:b', '2:c'], iterator_to_array($result, false));
    }

    public function test_map_indexed_empty(): void
    {
        $result = TransformGenerator::mapIndexed(
            static fn(int $idx, $val): string => sprintf('%d:%s', $idx, $val),
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_map_indexed_with_multibyte_string(): void
    {
        $result = TransformGenerator::mapIndexed(
            static fn(int $idx, $val): string => sprintf('%d:%s', $idx, $val),
            '🎉🎊',
        );

        self::assertSame(['0:🎉', '1:🎊'], iterator_to_array($result, false));
    }

    // ==================== filter tests ====================

    public function test_filter_basic(): void
    {
        $result = TransformGenerator::filter(
            static fn($x): bool => $x > 2,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_filter_none_match(): void
    {
        $result = TransformGenerator::filter(
            static fn($x): bool => $x > 10,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_filter_all_match(): void
    {
        $result = TransformGenerator::filter(
            static fn($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_filter_with_string(): void
    {
        $result = TransformGenerator::filter(
            static fn($char): bool => $char !== 'b',
            'abc',
        );

        self::assertSame(['a', 'c'], iterator_to_array($result, false));
    }

    // ==================== keep tests ====================

    public function test_keep_basic(): void
    {
        $result = TransformGenerator::keep(
            static fn($x): ?int => $x > 2 ? $x * 10 : null,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([30, 40, 50], iterator_to_array($result, false));
    }

    public function test_keep_all_null(): void
    {
        $result = TransformGenerator::keep(
            static fn($x): null => null,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_keep_preserves_falsy_non_null(): void
    {
        $result = TransformGenerator::keep(
            static fn($x): mixed => $x % 2 === 0 ? 0 : null,
            [1, 2, 3, 4],
        );

        self::assertSame([0, 0], iterator_to_array($result, false));
    }

    // ==================== keepIndexed tests ====================

    public function test_keep_indexed_basic(): void
    {
        $result = TransformGenerator::keepIndexed(
            static fn(int $idx, $val): ?string => $idx % 2 === 0 ? $val : null,
            ['a', 'b', 'c', 'd', 'e'],
        );

        self::assertSame(['a', 'c', 'e'], iterator_to_array($result, false));
    }

    public function test_keep_indexed_empty(): void
    {
        $result = TransformGenerator::keepIndexed(
            static fn(int $idx, $val): ?string => $val,
            [],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_keep_indexed_with_multibyte_string(): void
    {
        $result = TransformGenerator::keepIndexed(
            static fn(int $idx, $val): ?string => $idx === 1 ? sprintf('%d:%s', $idx, $val) : null,
            '🎉🎊',
        );

        self::assertSame(['1:🎊'], iterator_to_array($result, false));
    }
}
