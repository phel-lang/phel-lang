<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\Counts;
use PHPUnit\Framework\TestCase;

final class CountsTest extends TestCase
{
    public function test_default_construction_zeroes_every_counter(): void
    {
        $counts = new Counts();

        self::assertSame(0, $counts->pass);
        self::assertSame(0, $counts->failed);
        self::assertSame(0, $counts->error);
        self::assertSame(0, $counts->skipped);
        self::assertSame(0, $counts->total);
        self::assertFalse($counts->hasFailures());
    }

    public function test_from_array_picks_known_keys(): void
    {
        $counts = Counts::fromArray([
            'pass' => 5,
            'failed' => 1,
            'error' => 2,
            'skipped' => 3,
            'total' => 11,
        ]);

        self::assertSame(5, $counts->pass);
        self::assertSame(1, $counts->failed);
        self::assertSame(2, $counts->error);
        self::assertSame(3, $counts->skipped);
        self::assertSame(11, $counts->total);
    }

    public function test_from_array_clamps_negative_values_to_zero(): void
    {
        $counts = Counts::fromArray(['pass' => -5, 'total' => -1]);

        self::assertSame(0, $counts->pass);
        self::assertSame(0, $counts->total);
    }

    public function test_from_array_accepts_numeric_strings(): void
    {
        $counts = Counts::fromArray(['pass' => '7', 'total' => '7']);

        self::assertSame(7, $counts->pass);
        self::assertSame(7, $counts->total);
    }

    public function test_from_array_ignores_garbage(): void
    {
        $counts = Counts::fromArray(['pass' => 'NaN', 'total' => null]);

        self::assertSame(0, $counts->pass);
        self::assertSame(0, $counts->total);
    }

    public function test_add_accumulates_each_field(): void
    {
        $a = new Counts(pass: 2, failed: 1, total: 3);
        $b = new Counts(pass: 4, failed: 0, error: 1, total: 5);

        $a->add($b);

        self::assertSame(6, $a->pass);
        self::assertSame(1, $a->failed);
        self::assertSame(1, $a->error);
        self::assertSame(8, $a->total);
    }

    public function test_has_failures_when_failed_or_error_positive(): void
    {
        self::assertTrue(new Counts(failed: 1)->hasFailures());
        self::assertTrue(new Counts(error: 1)->hasFailures());
        self::assertFalse(new Counts(pass: 100)->hasFailures());
    }
}
