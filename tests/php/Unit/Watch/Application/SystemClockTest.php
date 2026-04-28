<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application;

use Phel\Watch\Application\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function test_now_ms_is_monotonic_across_calls(): void
    {
        $clock = new SystemClock();

        $first = $clock->nowMs();
        $second = $clock->nowMs();

        self::assertGreaterThanOrEqual($first, $second);
    }

    public function test_sleep_ms_with_zero_is_a_noop(): void
    {
        $clock = new SystemClock();

        $start = $clock->nowMs();
        $clock->sleepMs(0);
        $end = $clock->nowMs();

        self::assertLessThan(50, $end - $start);
    }

    public function test_sleep_ms_with_negative_is_a_noop(): void
    {
        $clock = new SystemClock();

        $start = $clock->nowMs();
        $clock->sleepMs(-100);
        $end = $clock->nowMs();

        self::assertLessThan(50, $end - $start);
    }

    public function test_sleep_ms_advances_now(): void
    {
        $clock = new SystemClock();

        $start = $clock->nowMs();
        $clock->sleepMs(5);
        $end = $clock->nowMs();

        // usleep precision is loose; just confirm some time did elapse.
        self::assertGreaterThanOrEqual(1, $end - $start);
    }
}
