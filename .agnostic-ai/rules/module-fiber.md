---
name: module-fiber
description: 'Load when editing src/php/Fiber: promises, futures and the cooperative scheduler behind `phel.core` promise/future.'
scope: src/php/Fiber
---

# Fiber Module

Cooperative async primitives backing `phel.core`'s `promise`/`deliver` and `future-call`/`future-fiber`.

`FiberFacade` has no interface (`phel.core` builds it directly). Internal plumbing, outside the public PHP semver surface (ADR 0021). No Provider; a leaf module.

## Key Constraints

- `future()` enqueues its body on the scheduler at construction, not at `await()`.
- `await(x, null)` waits unbounded via `Scheduler::await`; with a timeout it calls `derefWithTimeout(ms, null)` and returns `null` on timeout. `derefWithTimeout(0, fallback)` returns `fallback` at once (Clojure semantics).
- The scheduler is single-threaded and cooperative: CPU-bound work blocks until a fiber yields. Inside a Fiber, `await()` yields via `Fiber::suspend()`; outside, it drains the ready queue and sleeps `FiberConfig::defaultSleepMicroseconds()` (500µs) between ticks.
- `Scheduler::instance()` is a process-wide singleton. Tests MUST call `Scheduler::setInstance(null)` in tearDown or inject an isolated instance.
- `Promise::deliver()` is idempotent: first call returns `true`, later calls `false`.
- `Promise::deref()` returns a delivered Throwable verbatim; `Future::deref()` rethrows what its body threw.
- Cancellation is cooperative: `cancel()` sets a flag the body checks with `isCancelled()`.
