# Async Module Guide

Phel ships two cooperating concurrency layers over PHP fibers, both available **from `phel.core` without an explicit require** so `.cljc` portable code mirrors `clojure.core`'s defaults. The single exception is `delay`, which stays in `phel.async` because Phel's `delay` is a sleep primitive while `clojure.core/delay` is a lazy-thunk wrapper — keeping the names separated avoids silently hijacking Clojure semantics.

## Contents

- [Overview](#overview)
- [When to use which](#when-to-use-which)
- [AMPHP layer reference](#amphp-layer-reference)
- [Fiber layer reference](#fiber-layer-reference)
- [Shared primitives](#shared-primitives)
- [Error and cancellation model](#error-and-cancellation-model)
- [Interop](#interop)
- [Pitfalls](#pitfalls)
- [Recipes](#recipes)

## Overview

**AMPHP-backed layer.** Built on `amphp/amp`. An event loop drives fiber-based IO, timers, and combinators. `async`, `await`, `await-all`, `await-any`, `pmap`, `future`, `future-cancel`, and `future-cancelled?` are in `phel.core`; `delay` is in `phel.async`. Use this layer when you already run inside an event loop or when you need timers, IO multiplexing, or fan-out across many Futures.

**Fiber-backed layer.** Uses a cooperative single-threaded scheduler in `\Phel\Fiber\FiberFacade` with no event loop. `promise`, `deliver`, `future-call`, `future-fiber`, and `future?` are in `phel.core`. Safe to call from the top level of a script or REPL and convenient for CPU coordination, producer/consumer handoffs, and lightweight deref-with-timeout.

## When to use which

| Need | Use |
|------|-----|
| Top-level script, no event loop | fiber layer (`future-fiber`, `promise`, `deliver`) |
| CPU coordination, producer/consumer handoff | fiber layer |
| IO parallelism, timers, fan-out | AMPHP layer (`async`, `delay`, `await-all`, `await-any`) |
| Mixing AMPHP-based libs (HTTP clients, servers) | AMPHP layer |
| Clojure-style `future` with `deref` timeout inside a loop | AMPHP `future` inside `async`, or fiber `future-call` at top level |

Rule of thumb: reach for the fiber layer first for plain scripts, and for the AMPHP layer whenever IO, timers, or existing AMPHP code are involved.

## AMPHP layer reference

### `async`

```phel
(async body...)
```

Schedules `body` on the AMPHP event loop in a fresh fiber. Returns an `Amp\Future`. Gotcha: needs an active event loop; call this inside `Amp\Loop::run` or a command that opens one. Exceptions raised inside the body surface when the Future is awaited.

```phel
(await (async (+ 1 2))) ;; => 3
```

### `await`

```phel
(await future)
```

Blocks the current fiber until the Future resolves, then returns its value. Accepts either a raw `Amp\Future` or a `PhelFuture` wrapper. Gotcha: must be called from inside a fiber.

### `delay` (in `phel.async`)

```phel
(delay seconds)
```

Suspends execution for `seconds` (float). At the top level it behaves like `php/sleep`; inside an `async`/`future` body it suspends the *fiber* (not the whole process) and the delay becomes cancellable via `future-cancel`. Uses `Amp\delay` under the hood.

> **Not Clojure's `delay`.** `clojure.core/delay` is a lazy-thunk wrapper, not a sleep. Phel's `delay` lives in `phel.async` (not `phel.core`) precisely so this difference stays visible to `.cljc` portable code.

```phel
(:require phel\async :refer [delay])

(async (delay 0.1) :done)
```

### `await-all`

```phel
(await-all futures)
```

Awaits every Future in the collection and returns a vector of their resolved values in order. Gotcha: if any Future fails, the exception propagates and the others keep running until their next checkpoint.

```phel
(await-all [(async (fetch :a)) (async (fetch :b))])
```

### `await-any`

```phel
(await-any futures)
```

Returns the value of the first Future to resolve. Gotcha: losing Futures are not cancelled for you; pair with `future-cancel` when that matters.

### `pmap`

```phel
(pmap f coll) (pmap f coll1 coll2 ...)
```

Concurrent `map` via fibers. Results are returned in input order. Gotcha: PHP fibers are cooperative on a single thread, so `pmap` overlaps IO-bound work (HTTP, DB, file IO) but does **not** parallelize CPU-bound computations across cores — unlike `clojure.core/pmap`, which uses a thread pool. ClojureScript and Basilisp follow the same single-threaded model.

### `future`

```phel
(future body...)
```

Wraps `body` in an `Amp\Future` and returns a `PhelFuture` supporting `deref`, `realized?`, 3-arg `deref` timeouts, `future-cancel`, `future-cancelled?`, and `future-done?`. Gotcha: requires a fiber context (call inside `async` or an AMPHP loop).

```phel
(async
  (let [f (future (do (delay 0.1) 99))]
    (deref f 50 :timeout)))
```

### `future-cancel`

```phel
(future-cancel f)
```

Signals the Future's internal `DeferredCancellation` token. Pending and subsequent `deref` calls throw `Amp\CancelledException` (or return the fallback for the 3-arg form). Gotcha: cancellation is cooperative; the body keeps running until it hits a cancellation-aware checkpoint.

### `future-cancelled?`

```phel
(future-cancelled? f)
```

Returns `true` once `future-cancel` has been called. Gotcha: does not imply the body has stopped; use `future-done?` to check terminal state.

## Fiber layer reference

### `promise`

```phel
(promise)
```

Returns a new unrealized promise. Single delivery: once set, the value is frozen. `deref` suspends cooperatively from inside a fiber, or drains the scheduler from the top level. Gotcha: not thread-safe across processes; it lives in a single PHP process.

### `deliver`

```phel
(deliver p value)
```

Delivers `value` to promise `p`. Returns `p` on first delivery, `nil` if already realized. Gotcha: idempotent in the sense that the second call is a no-op; the stored value never changes.

```phel
(let [p (promise)]
  (deliver p 1)
  (deliver p 2) ;; no-op
  @p)           ;; => 1
```

### `future-call`

```phel
(future-call f)
```

Runs the zero-arg function `f` in a new fiber via the cooperative scheduler. Returns a `PhelFiberFuture` that supports `deref`, `realized?`, `future-done?`, and `future-cancel`. Gotcha: `f` must be zero-arg; use a closure to capture state.

### `future-fiber`

```phel
(future-fiber body...)
```

Macro over `future-call`. Lets you write `@(future-fiber (expensive))` without an outer `async` block. Gotcha: unlike AMPHP `future`, this cannot take advantage of the event loop, so blocking PHP calls freeze the scheduler until they return.

### `future?`

```phel
(future? x)
```

Returns `true` if `x` is either a fiber-future or a `PhelFuture`. Useful when you receive Futures from code that may use either layer.

## Shared primitives

Phel's `deref` is overloaded and dispatches to the right layer at runtime.

| Form | Behavior |
|------|----------|
| `(deref x)` / `@x` | Block until realized. Fiber path suspends cooperatively; AMPHP path awaits via the event loop |
| `(deref x timeout-ms timeout-val)` | Return `timeout-val` if not realized within `timeout-ms`. Fiber path uses a deadline poll; AMPHP path uses `Future::await` with a `TimeoutCancellation` |
| `(realized? x)` | `true` once a value is available. Works for promises, fiber futures, and `PhelFuture` |
| `(future-done? x)` | For fiber futures, checks `isDone`; for anything else, falls back to `realized?`. Use this when you need "terminal state including cancellation", not just "value present" |

## Error and cancellation model

- **AMPHP path**: exceptions thrown inside a `future` or `async` body surface out of `await` / `deref`. Cancellation uses `Amp\DeferredCancellation`; after `future-cancel` any `deref` raises `Amp\CancelledException`, and the 3-arg form returns its fallback.
- **Fiber path**: exceptions thrown in a `future-call` body are re-raised on `deref`. `future-cancel` flips a flag checked at cooperative checkpoints; the 3-arg `deref` returns its fallback without waiting.
- **`deliver` is idempotent**: the first call wins and the return value tells you whether you set it. Use this to implement "first writer wins" handoffs without locks.

## Interop

- `->closure` converts a Phel function into a PHP `\Closure`. Many PHP libraries, including AMPHP and ReactPHP, type-hint `\Closure` and reject Phel's `AbstractFn` even though it is callable. Wrap before handing a Phel fn to such a library.
- Bare `Amp\Future` values returned by AMPHP libs can be passed to `await`, `await-all`, and `await-any` directly; no wrapping required.
- To feed a fiber-layer result to AMPHP code, deref it from inside an `async` block: `(async (use-value @(future-fiber ...)))`.

## Pitfalls

- **Calling `future` outside an event loop**. The AMPHP `future` macro needs a fiber context; use `future-fiber` for top-level scripts.
- **Mixing future types**. `future?` is the safe predicate when you might receive either; type-specific dispatch is already baked into `deref`, `realized?`, and `future-done?`.
- **CPU-bound `pmap`**. PHP fibers share one thread. If `f` is CPU-heavy, `pmap` will not speed it up and may add overhead. Shell out to workers for real parallelism.
- **Blocking PHP calls inside fiber futures**. `sleep`, `usleep`, synchronous `curl`, and blocking socket reads freeze the cooperative scheduler. Use `delay` (AMPHP) or non-blocking IO.

## Recipes

### Producer/consumer via `promise`

```phel
(ns demo\producer)

(let [inbox (promise)]
  (future-call (fn []
                 (deliver inbox {:event :ready :at (php/time)})))
  (println "got:" @inbox))
```

Run with `./bin/phel run demo/producer.phel`.

### Fan-out, fan-in with `await-all`

```phel
(ns demo\fanout
  (:require phel\async :refer [delay]))

(defn fetch [label ms]
  (async
    (delay (/ ms 1000))
    (str label ":" ms "ms")))

(println
  (await-all [(fetch :eu 80)
              (fetch :us 40)
              (fetch :asia 60)]))
```

Wall time tracks the slowest branch, not the sum.

### Timeout race with 3-arg `deref`

```phel
(ns demo\timeout)

(let [p (promise)]
  ;; No producer wired up: the deref expires and returns the fallback.
  (println (deref p 25 :timed-out)))
;; => :timed-out
```

### Cancel on first error

```phel
(ns demo\cancel-on-error
  (:require phel\async :refer [delay]))

(defn launch []
  (async
    (let [slow (future (do (delay 0.2) :slow))
          fast (future (do (delay 0.05)
                           (throw (php/new \RuntimeException "boom"))))]
      (try
        (await fast)
        (catch \RuntimeException e
          (future-cancel slow)
          (str "cancelled after: " (php/-> e (getMessage))))))))

(println (await (launch)))
```

`future-cancel` is cooperative, so `slow` finishes its current step before observing the cancellation, but any `deref` on it now throws `Amp\CancelledException`.

## See also

- [Example: `docs/examples/11_async-concurrency.phel`](examples/11_async-concurrency.phel)
- [`phel\http-client`](../src/phel/http-client.phel) for AMPHP-based HTTP calls that slot into this model
