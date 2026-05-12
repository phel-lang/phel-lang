# Fiber Module

Cooperative fiber primitives: promises, futures, and a single-threaded
scheduler used by `phel.core` for `promise`/`deliver` and
`future-call`/`future-fiber`.

## Gacela Pattern

- **Facade**: `FiberFacade` implements `FiberFacadeInterface`
- **Factory**: `FiberFactory` : creates Promise and Future; `withDefaultScheduler()` / `withScheduler(Scheduler)`
- **Config**: `FiberConfig` : default poll sleep (500 microseconds)
- **Provider**: `FiberProvider` : no cross-module dependencies

## Public API (Facade)

- `createPromise(): Promise` : single-delivery promise
- `future(callable): Future` : execute callable in a fiber
- `await(Awaitable, ?int = null): mixed` : wait for realization
- `scheduler(): Scheduler` : process-wide singleton

## Scheduler Semantics

- Single-threaded, cooperative. One FIFO ready-queue of suspended fibers.
- `Scheduler::instance()` exposes the process-wide singleton. Tests inject a fresh instance via `Scheduler::setInstance(...)` or `FiberFactory::withScheduler()`.
- `await(Awaitable)` inside a Fiber loops on `Fiber::suspend()` until realized. Outside a Fiber, drains the ready queue, sleeping briefly between ticks.
- Long CPU-bound work blocks the scheduler until the fiber yields. No preemption.

## Domain

- **Promise**: single-delivery. Waiting fibers poll via `Fiber::suspend()`. `derefWithTimeout(0, fallback)` returns fallback immediately.
- **Future**: wraps a callable in a Fiber. Captures return value or throwable. Cancellation is cooperative via `isCancelled()` flag.
- **Awaitable**: contract for `deref()`, `derefWithTimeout()`, `isRealized()`. Both Promise and Future implement it.

## Structure

```
Fiber/
├── Domain/        Awaitable, Scheduler, Promise, Future
└── Gacela files   FiberFacade, FiberFactory, FiberConfig, FiberProvider
```

## Key Constraints

- The scheduler is process-wide. Tests must call `Scheduler::setInstance(null)` in tearDown or use an isolated instance.
- Exceptions thrown inside a Future body are captured and rethrown only on `deref()`.
- `deliver` is idempotent: first call returns true, subsequent calls return false (silent no-ops).
- Zero-millisecond `derefWithTimeout` always returns the fallback (Clojure semantics).
