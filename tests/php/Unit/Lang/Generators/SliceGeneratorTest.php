<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use Generator;
use Phel\Lang\Generators\SliceGenerator;
use PHPUnit\Framework\TestCase;

final class SliceGeneratorTest extends TestCase
{
    public function test_take_basic(): void
    {
        $result = SliceGenerator::take(3, [1, 2, 3, 4, 5]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_more_than_available(): void
    {
        $result = SliceGenerator::take(10, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_zero(): void
    {
        $result = SliceGenerator::take(0, [1, 2, 3]);

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

        $result = SliceGenerator::take(2, $generator);
        self::assertSame([1, 2], iterator_to_array($result, false));
        // Generator may advance one extra during the break check, but should not process all 5
        self::assertLessThanOrEqual(3, $callCount);
    }

    public function test_take_while_basic(): void
    {
        $result = SliceGenerator::takeWhile(
            static fn($x): bool => $x < 4,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_while_none_match(): void
    {
        $result = SliceGenerator::takeWhile(
            static fn($x): bool => $x < 0,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_take_while_all_match(): void
    {
        $result = SliceGenerator::takeWhile(
            static fn($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    /**
     * Phel predicates return arbitrary values, not `bool`. Only `nil` and
     * `false` end the run; PHP truthiness would stop at the `0`.
     */
    public function test_take_while_does_not_stop_on_php_falsy_results(): void
    {
        $result = SliceGenerator::takeWhile(
            static fn(mixed $x): mixed => $x,
            [1, 0, '', 2, null, 3],
        );

        self::assertSame([1, 0, '', 2], iterator_to_array($result, false));
    }

    public function test_drop_while_does_not_stop_on_php_falsy_results(): void
    {
        $result = SliceGenerator::dropWhile(
            static fn(mixed $x): mixed => $x,
            [1, 0, '', false, 2],
        );

        self::assertSame([false, 2], iterator_to_array($result, false));
    }

    public function test_take_nth_basic(): void
    {
        $result = SliceGenerator::takeNth(2, [1, 2, 3, 4, 5, 6]);

        self::assertSame([1, 3, 5], iterator_to_array($result, false));
    }

    public function test_take_nth_every_third(): void
    {
        $result = SliceGenerator::takeNth(3, [1, 2, 3, 4, 5, 6, 7, 8, 9]);

        self::assertSame([1, 4, 7], iterator_to_array($result, false));
    }

    public function test_take_nth_one(): void
    {
        $result = SliceGenerator::takeNth(1, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_take_nth_negative(): void
    {
        $result = SliceGenerator::takeNth(-2, [1, 2, 3, 4, 5, 6]);

        self::assertSame([1, 3, 5], iterator_to_array($result, false));
    }

    public function test_take_nth_with_multibyte_string(): void
    {
        $result = SliceGenerator::takeNth(2, '🎉a🎊b');

        self::assertSame(['🎉', '🎊'], iterator_to_array($result, false));
    }

    public function test_drop_basic(): void
    {
        $result = SliceGenerator::drop(2, [1, 2, 3, 4, 5]);

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_drop_more_than_available(): void
    {
        $result = SliceGenerator::drop(10, [1, 2, 3]);

        self::assertSame([], iterator_to_array($result, false));
    }

    public function test_drop_zero(): void
    {
        $result = SliceGenerator::drop(0, [1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_drop_while_basic(): void
    {
        $result = SliceGenerator::dropWhile(
            static fn($x): bool => $x < 3,
            [1, 2, 3, 4, 5],
        );

        self::assertSame([3, 4, 5], iterator_to_array($result, false));
    }

    public function test_drop_while_none_match(): void
    {
        $result = SliceGenerator::dropWhile(
            static fn($x): bool => $x < 0,
            [1, 2, 3],
        );

        self::assertSame([1, 2, 3], iterator_to_array($result, false));
    }

    public function test_drop_while_all_match(): void
    {
        $result = SliceGenerator::dropWhile(
            static fn($x): bool => $x > 0,
            [1, 2, 3],
        );

        self::assertSame([], iterator_to_array($result, false));
    }
}
