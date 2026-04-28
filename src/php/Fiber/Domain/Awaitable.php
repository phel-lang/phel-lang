<?php

declare(strict_types=1);

namespace Phel\Fiber\Domain;

/**
 * Common contract for values that can be blocked on via the scheduler.
 *
 * Both Promise and Future implement this so {@see Scheduler::await} can
 * park the caller until the value is ready.
 */
interface Awaitable
{
    /**
     * Whether this awaitable is in a final state (delivered, failed,
     * or cancelled). A realized awaitable never transitions back.
     */
    public function isRealized(): bool;

    /**
     * Blocks until realized and returns the stored value. If the stored
     * value is a Throwable it is rethrown.
     *
     * When called from inside a Fiber, suspends cooperatively. Outside a
     * Fiber context, falls back to a bounded usleep poll.
     */
    public function deref(): mixed;

    /**
     * Blocks up to $timeoutMs milliseconds and returns the stored value.
     * Returns $timeoutVal on timeout. 0 returns $timeoutVal immediately.
     */
    public function derefWithTimeout(int $timeoutMs, mixed $timeoutVal): mixed;
}
