<?php

declare(strict_types=1);

namespace Phel\Fiber\Domain;

use Fiber;
use Throwable;

/**
 * Fiber-backed future. Wraps a callable executed inside a new Fiber that
 * runs on the scheduler. The return value (or thrown exception) is stored
 * in an internal Promise.
 *
 * Cancellation is cooperative: setting the cancelled flag causes the next
 * cooperative yield point to see the flag. Callers should inspect
 * {@see isCancelled()} from inside their body if they need early exit.
 */
final class Future implements Awaitable
{
    private readonly Promise $promise;

    private readonly Fiber $fiber;

    private bool $cancelled = false;

    private bool $failed = false;

    private ?Throwable $error = null;

    /**
     * @param callable():mixed $body
     */
    public function __construct(
        private readonly Scheduler $scheduler,
        callable $body,
    ) {
        $this->promise = new Promise($scheduler);
        $this->fiber = new Fiber(function () use ($body): void {
            try {
                $result = $body();
                $this->promise->deliver($result);
            } catch (Throwable $throwable) {
                $this->failed = true;
                $this->error = $throwable;
                $this->promise->deliver(null);
            }
        });

        $scheduler->enqueue($this->fiber);
    }

    public function isRealized(): bool
    {
        return $this->promise->isRealized();
    }

    public function isDone(): bool
    {
        if ($this->promise->isRealized()) {
            return true;
        }

        return $this->cancelled;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function deref(): mixed
    {
        $value = $this->scheduler->await($this->promise);

        if ($this->failed && $this->error instanceof Throwable) {
            throw $this->error;
        }

        return $value;
    }

    public function derefWithTimeout(int $timeoutMs, mixed $timeoutVal): mixed
    {
        if ($this->cancelled && !$this->promise->isRealized()) {
            return $timeoutVal;
        }

        $value = $this->promise->derefWithTimeout($timeoutMs, $timeoutVal);

        if ($this->promise->isRealized() && $this->failed && $this->error instanceof Throwable) {
            throw $this->error;
        }

        return $value;
    }
}
