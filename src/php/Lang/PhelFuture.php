<?php

declare(strict_types=1);

namespace Phel\Lang;

use Amp\Future;

/**
 * Adapter wrapping an Amphp Future to expose Phel's deref/realized? protocol.
 *
 * Bridging Clojure-style future semantics onto Amphp:
 *   - `deref` → `await()` — blocks the current fiber until the underlying future completes
 *   - `isRealized` → `isComplete()` — non-blocking state check
 *
 * Note: `deref` must be called from inside a fiber (an `async` block or the event
 * loop), matching Amphp's cooperative-concurrency model. Calling `deref` on a
 * PhelFuture outside a fiber context is an Amphp runtime error by design.
 */
final readonly class PhelFuture
{
    public function __construct(
        private Future $future,
    ) {}

    public function deref(): mixed
    {
        return $this->future->await();
    }

    public function isRealized(): bool
    {
        return $this->future->isComplete();
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
