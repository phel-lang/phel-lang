<?php

declare(strict_types=1);

namespace Phel\Fiber;

use Gacela\Framework\AbstractConfig;

/**
 * Scheduler configuration.
 *
 * The default sleep is the latency/CPU trade-off for the top-level busy-poll
 * used when awaiting outside a Fiber: shorter sleeps lower wake-up latency but
 * burn more CPU while idle-waiting, longer sleeps do the reverse. The default
 * of 500 microseconds balances the two; override it when a workload is
 * latency-sensitive or, conversely, mostly idle.
 */
final class FiberConfig extends AbstractConfig
{
    private const int DEFAULT_SLEEP_MICROSECONDS = 500;

    public static function defaultSleepMicroseconds(): int
    {
        return self::DEFAULT_SLEEP_MICROSECONDS;
    }
}
