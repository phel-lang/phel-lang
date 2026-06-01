# Fiber Module

Cooperative fiber primitives: promises, futures, and a single-threaded scheduler used by `phel.core` for `promise`/`deliver` and `future-call`/`future-fiber`.

## Gacela Pattern

- **Facade**: `FiberFacade` implements `FiberFacadeInterface`
- **Factory**: `FiberFactory` creates Promise, Future, and wires the scheduler
- **Config**: `FiberConfig` with default poll sleep (500 microseconds)
- **Provider**: `FiberProvider` (no cross-module dependencies)

## Public API (Facade)

- `createPromise(): Promise` creates a single-delivery promise
- `future(callable $body): Future` executes callable in a fiber
- `await(Awaitable, ?int $timeoutMs = null): mixed` blocks until realized
- `scheduler(): Scheduler` returns process-wide singleton

## Scheduler Semantics

- Single-threaded, cooperative. FIFO ready-queue of suspended fibers.
- `Scheduler::instance()` exposes process-wide singleton; tests inject fresh instance via `Scheduler::setInstance()`.
- Inside a Fiber: `await()` cooperatively yields via `Fiber::suspend()` until realized.
- Outside a Fiber: `await()` drains ready queue, sleeping briefly between ticks.
- CPU-bound work blocks until fiber yields. No preemption.

## Domain

- **Promise**: single-delivery, implements Awaitable and FnInterface. `derefWithTimeout(0, fallback)` returns fallback immediately.
- **Future**: wraps callable in Fiber; captures return value or exception. Cancellation is cooperative via `isCancelled()` flag and `cancel()` method.
- **Awaitable**: contract for `isRealized()`, `deref()`, `derefWithTimeout(int, mixed)`. Implemented by Promise and Future.

## Structure

```
Fiber/
├── Domain/        Awaitable, Scheduler, Promise, Future
└── Gacela files   FiberFacade, FiberFacadeInterface, FiberFactory, FiberConfig, FiberProvider
```

## Key Constraints

- Scheduler is process-wide singleton. Tests must call `Scheduler::setInstance(null)` in tearDown or inject isolated instance.
- Exceptions inside Future body are captured and rethrown on `deref()`.
- `Promise::deliver()` is idempotent: first call returns true, subsequent calls return false.
- `derefWithTimeout(0, fallback)` always returns fallback immediately (Clojure semantics).
