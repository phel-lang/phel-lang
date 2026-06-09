# Fiber Module

Cooperative fiber primitives: promises, futures, and a single-threaded scheduler used by `phel.core` for `promise`/`deliver` and `future-call`/`future-fiber`. No cross-module dependencies; `FiberConfig` holds default poll sleep (500 microseconds).

## Public API (Facade)

- `createPromise(): Promise`: single-delivery promise
- `future(callable $body): Future`: executes callable in a fiber
- `await(Awaitable, ?int $timeoutMs = null): mixed`: blocks until realized
- `scheduler(): Scheduler`: process-wide singleton

## Semantics

- Scheduler: single-threaded, cooperative, FIFO ready-queue of suspended fibers. No preemption; CPU-bound work blocks until fiber yields.
- `await()` inside a Fiber yields via `Fiber::suspend()` until realized; outside a Fiber it drains the ready queue, sleeping briefly between ticks.
- `Awaitable` contract (`isRealized()`, `deref()`, `derefWithTimeout(int, mixed)`) implemented by Promise and Future.
- Future cancellation is cooperative: `cancel()` sets `isCancelled()` flag.

## Key Constraints

- Scheduler is process-wide singleton (`Scheduler::instance()`). Tests must call `Scheduler::setInstance(null)` in tearDown or inject isolated instance.
- Exceptions inside Future body are captured and rethrown on `deref()`.
- `Promise::deliver()` is idempotent: first call returns true, subsequent calls return false.
- `derefWithTimeout(0, fallback)` always returns fallback immediately (Clojure semantics).
- Promise implements `FnInterface` (callable).
