<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Monotonic clock indirection so unit tests can freeze time.
 */
interface ClockInterface
{
    /**
     * Current time in milliseconds since the Unix epoch.
     */
    public function nowMs(): int;

    /**
     * Sleep for the given number of milliseconds.
     */
    public function sleepMs(int $ms): void;
}
