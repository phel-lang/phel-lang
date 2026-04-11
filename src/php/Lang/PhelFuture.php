<?php

declare(strict_types=1);

namespace Phel\Lang;

use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\TimeoutCancellation;

/**
 * Adapter wrapping an Amphp Future to expose Phel's deref/realized? protocol.
 *
 * Bridging Clojure-style future semantics onto Amphp:
 *   - `deref` → `await()` — blocks the current fiber until the underlying future completes
 *   - `derefWithTimeout` → `await()` with a composite TimeoutCancellation
 *   - `isRealized` → `isComplete()` (or cancelled) — non-blocking state check
 *   - `cancel` / `isCancelled` — signals the internal DeferredCancellation token
 *
 * Note: `deref` must be called from inside a fiber (an `async` block or the event
 * loop), matching Amphp's cooperative-concurrency model. Calling `deref` on a
 * PhelFuture outside a fiber context is an Amphp runtime error by design.
 *
 * Cancellation semantics: `cancel()` signals the internal Amphp `DeferredCancellation`
 * token. Any pending or subsequent `deref()` call raises `CancelledException`, and
 * `derefWithTimeout()` returns the timeout value. Because Amphp fibers are
 * cooperative, the future's body keeps running until its next `await`/cancellation
 * checkpoint — from the caller's perspective the future behaves as cancelled, but
 * the underlying work may still complete in the background. This differs slightly
 * from Clojure's JVM-thread interrupt semantics.
 */
final class PhelFuture
{
    private bool $cancelled = false;

    public function __construct(
        private readonly Future $future,
        private readonly DeferredCancellation $cancellation,
    ) {}

    public function deref(): mixed
    {
        return $this->future->await($this->cancellation->getCancellation());
    }

    /**
     * Blocks at most `$timeoutMs` milliseconds waiting for the future to complete.
     *
     * Returns the future's value if it completes in time, otherwise returns
     * `$timeoutVal`. Also returns `$timeoutVal` if the future was cancelled via
     * `cancel()` (whether before or during this call).
     */
    public function derefWithTimeout(int $timeoutMs, mixed $timeoutVal): mixed
    {
        if ($timeoutMs <= 0) {
            return $timeoutVal;
        }

        $seconds = $timeoutMs / 1000;
        $timeoutToken = new TimeoutCancellation($seconds);
        $composite = new CompositeCancellation(
            $this->cancellation->getCancellation(),
            $timeoutToken,
        );

        try {
            return $this->future->await($composite);
        } catch (CancelledException) {
            return $timeoutVal;
        }
    }

    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        $this->cancellation->cancel();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isRealized(): bool
    {
        if ($this->future->isComplete()) {
            return true;
        }

        return $this->cancelled;
    }

    /**
     * Exposes the underlying Amp\Future for interop with code that expects
     * the raw Amphp type (e.g. `await-all`, `await-any`).
     */
    public function unwrap(): Future
    {
        return $this->future;
    }
}
