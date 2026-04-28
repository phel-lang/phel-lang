<?php

declare(strict_types=1);

namespace Phel\Fiber\Domain;

use Fiber;
use Phel\Lang\FnInterface;

use function microtime;
use function usleep;

/**
 * Single-delivery promise. Once delivered, the value is frozen and
 * subsequent calls to {@see deliver()} are no-ops.
 *
 * Fibers that deref before delivery cooperatively yield via
 * `Fiber::suspend()` and resume on the next scheduler tick, checking
 * the delivered flag each time. Top-level callers (no active Fiber)
 * drain the scheduler's ready queue and sleep briefly between polls.
 */
final class Promise implements Awaitable, FnInterface
{
    private bool $delivered = false;

    private mixed $value = null;

    public function __construct(
        private readonly Scheduler $scheduler,
    ) {}

    /**
     * Promise-as-IFn, matching Clojure: `(p val)` delivers `val` and
     * returns the promise (or the existing value when already delivered);
     * `(p)` blocks until the promise is realized and returns the value.
     */
    public function __invoke(mixed ...$args): mixed
    {
        if ($args === []) {
            return $this->deref();
        }

        $this->deliver($args[0]);

        return $this;
    }

    /**
     * Deliver $value and freeze the promise. Returns true on the first
     * delivery, false when the promise is already delivered.
     */
    public function deliver(mixed $value): bool
    {
        if ($this->delivered) {
            return false;
        }

        $this->delivered = true;
        $this->value = $value;
        return true;
    }

    public function isRealized(): bool
    {
        return $this->delivered;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function deref(): mixed
    {
        if (Fiber::getCurrent() instanceof Fiber) {
            while (!$this->pollDelivered()) {
                Fiber::suspend();
            }

            return $this->value;
        }

        while (!$this->pollDelivered()) {
            if (!$this->scheduler->tick()) {
                usleep($this->scheduler->sleepMicroseconds());
            }
        }

        return $this->value;
    }

    public function derefWithTimeout(int $timeoutMs, mixed $timeoutVal): mixed
    {
        if ($this->pollDelivered()) {
            return $this->value;
        }

        if ($timeoutMs <= 0) {
            return $timeoutVal;
        }

        $deadline = microtime(true) + ((float) $timeoutMs / 1000.0);
        $current = Fiber::getCurrent();

        while (!$this->pollDelivered()) {
            if (microtime(true) >= $deadline) {
                return $timeoutVal;
            }

            if ($current instanceof Fiber) {
                Fiber::suspend();
                continue;
            }

            if (!$this->scheduler->tick()) {
                usleep($this->scheduler->sleepMicroseconds());
            }
        }

        return $this->value;
    }

    /**
     * Re-read the delivered flag through a method call so PHPStan cannot
     * fold it into a compile-time constant inside the deref poll loops.
     * The flag is flipped as a side effect of {@see deliver()} called from
     * a concurrently-running fiber, which no static analyser can see.
     *
     * The self-assignment below is intentional: writing the property back to
     * itself marks the method as impure to PHPStan without changing state.
     *
     * @phpstan-impure
     */
    private function pollDelivered(): bool
    {
        $snapshot = $this->delivered;
        $this->delivered = $snapshot;
        return $snapshot;
    }
}
