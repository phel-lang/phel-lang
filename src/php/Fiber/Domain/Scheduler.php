<?php

declare(strict_types=1);

namespace Phel\Fiber\Domain;

use Fiber;
use Throwable;

use function count;
use function usleep;

/**
 * Cooperative single-threaded scheduler for Phel fibers.
 *
 * Runs a FIFO ready-queue of suspended fibers, resuming them one at a time.
 * When a fiber yields (`Fiber::suspend()`) it is re-enqueued at the tail so
 * sibling fibers can run. Long CPU-bound work inside a fiber blocks the
 * scheduler until the fiber cooperatively yields.
 *
 * Usable as a process-wide singleton via {@see instance()} or injected as an
 * instance for tests.
 */
final class Scheduler
{
    private static ?self $instance = null;

    /** @var list<Fiber> */
    private array $ready = [];

    private int $sleepUsec = 500;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Replace or clear the process-wide singleton. Tests use this to swap
     * in an isolated instance; production code never touches it.
     */
    public static function setInstance(?self $scheduler): void
    {
        self::$instance = $scheduler;
    }

    /**
     * How long the top-level busy-poll sleeps between checks when waiting
     * on an unrealized awaitable outside a Fiber. Defaults to 500 microseconds.
     */
    public function setSleepMicroseconds(int $sleepUsec): void
    {
        $this->sleepUsec = max(0, $sleepUsec);
    }

    public function sleepMicroseconds(): int
    {
        return $this->sleepUsec;
    }

    /**
     * Enqueue a suspended fiber for later resumption. Terminated fibers are
     * silently dropped so callers do not need to pre-filter.
     */
    public function enqueue(Fiber $fiber): void
    {
        if ($fiber->isTerminated()) {
            return;
        }

        $this->ready[] = $fiber;
    }

    public function readyCount(): int
    {
        return count($this->ready);
    }

    public function hasReady(): bool
    {
        return $this->ready !== [];
    }

    /**
     * Resume exactly one ready fiber. Returns true if a fiber was advanced,
     * false when the queue was empty.
     */
    public function tick(): bool
    {
        if ($this->ready === []) {
            return false;
        }

        $fiber = array_shift($this->ready);
        $this->advance($fiber);
        return true;
    }

    /**
     * Drain the ready queue until empty. Intended for top-level loops that
     * need every scheduled fiber to make progress. Fibers that re-suspend
     * during a tick are re-enqueued by the awaitable they block on, so this
     * loop terminates only when nothing is left to run.
     */
    public function runUntilIdle(): void
    {
        while ($this->tick()) {
            // keep going until the queue empties
        }
    }

    /**
     * Block until $awaitable is realized, then return its stored value.
     *
     * If called inside a Fiber, suspends cooperatively so other fibers can
     * progress. Outside a Fiber, drains the ready queue then sleeps between
     * checks to avoid burning CPU.
     */
    public function await(Awaitable $awaitable): mixed
    {
        if (Fiber::getCurrent() instanceof Fiber) {
            while (!$awaitable->isRealized()) {
                Fiber::suspend();
            }

            return $awaitable->deref();
        }

        while (!$awaitable->isRealized()) {
            if (!$this->tick()) {
                usleep($this->sleepUsec);
            }
        }

        return $awaitable->deref();
    }

    private function advance(Fiber $fiber): void
    {
        if ($fiber->isTerminated()) {
            return;
        }

        try {
            if (!$fiber->isStarted()) {
                $fiber->start();
            } elseif ($fiber->isSuspended()) {
                $fiber->resume();
            }
        } catch (Throwable) {
            // Fiber body raised; its owning Future (if any) has already
            // captured the throwable. Drop the fiber here so the scheduler
            // does not re-enqueue it.
            return;
        }

        if ($this->stillRunnable($fiber)) {
            $this->ready[] = $fiber;
        }
    }

    /**
     * Opaque check so static analysers do not treat the post-run fiber state
     * as a compile-time constant: `start()`/`resume()` transition the fiber
     * internally and PHPStan cannot see the mutation through the extension.
     *
     * @phpstan-impure
     */
    private function stillRunnable(Fiber $fiber): bool
    {
        return !$fiber->isTerminated() && $fiber->isSuspended();
    }
}
