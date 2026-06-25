# Fiber Module

Cooperative async primitives (promises, futures, single-threaded scheduler) backing `phel.core`'s `promise`/`deliver` and `future-call`/`future-fiber`.

## Public API (Facade)

| Method | Returns | Notes |
|--------|---------|-------|
| `createPromise()` | `Promise` | single-delivery promise |
| `future(callable $body)` | `Future` | body enqueued on the scheduler eagerly at construction, not deferred to `await()` |
| `await(Awaitable, ?int $timeoutMs = null)` | `mixed` | `null` timeout → unbounded `Scheduler::await`; with timeout → `derefWithTimeout(ms, null)`, returns `null` on timeout |
| `scheduler()` | `Scheduler` | process-wide singleton |

## Dependencies

- None. `FiberProvider` is empty; no cross-module facades.
- `FiberConfig::defaultSleepMicroseconds()` = 500µs (top-level busy-poll sleep when awaiting outside a Fiber).

## Structure

| Path | Purpose |
|------|---------|
| `Domain/Awaitable.php` | contract: `isRealized()`, `deref()`, `derefWithTimeout(int, mixed)` — implemented by Promise + Future |
| `Domain/Promise.php` | single-delivery; implements `Awaitable` + `Phel\Lang\FnInterface` (callable) |
| `Domain/Future.php` | runs a callable in a `Fiber`; cooperative cancel |
| `Domain/Scheduler.php` | FIFO ready-queue, singleton via `instance()`/`setInstance()` |

## Key Constraints

- Scheduler is single-threaded, cooperative, no preemption: CPU-bound work blocks until a fiber yields.
- `await()` inside a Fiber yields via `Fiber::suspend()` until realized; outside a Fiber it drains the ready queue, sleeping between ticks.
- Scheduler is a process-wide singleton (`Scheduler::instance()`). Tests MUST call `Scheduler::setInstance(null)` in tearDown or inject an isolated instance.
- `Promise::deliver()` is idempotent: first call returns `true`, later calls `false`.
- `Promise::deref()` returns the delivered value verbatim even if it is a Throwable; `Future::deref()` rethrows the exception its body threw (captured during execution).
- `derefWithTimeout(0, fallback)` returns `fallback` immediately (Clojure semantics).
- Future cancellation is cooperative: `cancel()` sets the flag; the body checks `isCancelled()` for early exit.
