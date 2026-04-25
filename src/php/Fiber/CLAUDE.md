# Fiber Module

Cooperative fiber primitives: promises, futures, and a single-threaded
scheduler used by `phel\core` for `promise`/`deliver` and
`future-call`/`future-fiber`.

## Gacela Pattern

- **Facade**: `FiberFacade` implements `FiberFacadeInterface` — plain
  final class, instantiated directly (no Gacela factory inheritance) so
  it can share the process-wide singleton scheduler without container
  bootstrap.
- **Factory**: `FiberFactory` — builds `Promise` and `Future` bound to
  an injected `Scheduler`; has `withDefaultScheduler()` for prod and
  `withScheduler(Scheduler)` for tests.
- **Config**: `FiberConfig` — default top-level busy-poll sleep (500 microseconds).
- **Provider**: `FiberProvider` — no cross-module dependencies.

## Public API (Facade)

- `createPromise(): Promise`
- `future(callable $body): Future`
- `await(Awaitable $a, ?int $timeoutMs = null): mixed`
- `scheduler(): Scheduler`

## Scheduler semantics

- Single-threaded, cooperative. One FIFO ready-queue of suspended fibers.
- `Scheduler::instance()` exposes the process-wide singleton. Tests inject
  an isolated instance via `Scheduler::setInstance(...)` or by passing a
  fresh scheduler to `FiberFactory::withScheduler()`.
- `await(Awaitable)`:
  - Inside a Fiber: loops on `Fiber::suspend()` until realized.
  - Outside a Fiber: drains the ready queue, sleeping briefly between
    ticks to avoid CPU burn at the top level.
- Long CPU-bound work inside a fiber blocks the scheduler until it
  cooperatively yields. This is by design — there is no preemption.

## Domain

- `Promise` — single-delivery. Waiting fibers poll via `Fiber::suspend()`
  and rely on the scheduler to resume them on the next tick; they
  re-check the delivered flag each wake-up. `derefWithTimeout(0, fallback)`
  returns the fallback immediately.
- `Future` — wraps a callable executed inside a Fiber. Captures the
  return value (or thrown throwable) in an internal Promise.
  Cancellation is cooperative: a flag visible to the body via
  `isCancelled()`; deref on an unrealized cancelled future returns the
  timeout fallback (3-arg form) or keeps blocking in the default form.
- `Awaitable` — common contract for `deref`, `derefWithTimeout`,
  `isRealized`. Both Promise and Future implement it.

## Structure

```
Fiber/
|-- Domain/           Awaitable, Scheduler, Promise, Future
+-- Gacela files      FiberFacade, FiberFacadeInterface, FiberFactory,
                      FiberConfig, FiberProvider
```

## Key Constraints

- The scheduler is process-wide. Tests that interact with the singleton
  must restore `Scheduler::setInstance(null)` in tearDown or use an
  isolated instance.
- Exceptions thrown inside a Future body are captured and rethrown only
  on `deref()`. The scheduler never propagates throwables out of
  `tick()`.
- `deliver` is idempotent: first call returns `true`, subsequent calls
  return `false` and are silent no-ops.
- Zero-millisecond `derefWithTimeout` always returns the fallback,
  matching Clojure semantics.
