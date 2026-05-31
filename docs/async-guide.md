# Async Module Guide

Two concurrency layers over PHP fibers. Most primitives live in `phel.core` (no require). `delay` lives in `phel.async` because Phel's `delay` sleeps, unlike `clojure.core/delay` (a lazy-thunk wrapper).

## Overview

**AMPHP-backed layer.** Built on `amphp/amp`. An event loop drives fiber-based IO, timers, and combinators. `async`, `await`, `await-all`, `await-any`, `pmap`, `future`, `future-cancel`, `future-cancelled?` in `phel.core`; `delay` in `phel.async`. Use inside an event loop, or for timers, IO multiplexing, or fan-out across many Futures.

**Fiber-backed layer.** Cooperative single-threaded scheduler in `\Phel\Fiber\FiberFacade`, no event loop. `promise`, `deliver`, `future-call`, `future-fiber`, `future?` in `phel.core`. Safe at the top level of a script or REPL. Good for CPU coordination, producer/consumer handoffs, lightweight deref-with-timeout.

## When to use which

| Need | Use |
|------|-----|
| Top-level script, no event loop | fiber layer (`future-fiber`, `promise`, `deliver`) |
| CPU coordination, producer/consumer handoff | fiber layer |
| IO parallelism, timers, fan-out | AMPHP layer (`async`, `delay`, `await-all`, `await-any`) |
| Mixing AMPHP-based libs (HTTP clients, servers) | AMPHP layer |
| `future` with `deref` timeout inside a loop | AMPHP `future` inside `async`, or fiber `future-call` at top level |

Rule of thumb: fiber layer for plain scripts; AMPHP layer when IO, timers, or AMPHP code are involved.

## AMPHP layer reference

### `async`

```phel
(async body...)
```

Schedules `body` on the AMPHP event loop in a fresh fiber. Returns `Amp\Future`. Amp v3 manages the event loop automatically; no explicit `Loop::run` call needed. Exceptions in the body surface when awaited.

```phel
(await (async (+ 1 2))) ;; => 3
```

### `await`

```phel
(await future)
```

Blocks the current fiber until the Future resolves, then returns its value. Accepts a raw `Amp\Future` or a `Future` wrapper. Must be called from inside a fiber.

### `^:async` on `defn`

`^:async` wraps the body in `(async ...)`. The function returns `Amp\Future` for callers to `await`.

```phel
(defn ^:async fetch [url]
  (await (http-get url)))

(await (fetch "https://example.com"))
```

Multi-arity bodies are wrapped per arity. `^{:async false}` opts out without removing the metadata key.

### `delay` (in `phel.async`)

```phel
(delay seconds)
```

Suspends for `seconds` (float). At top level behaves like `php/sleep`. Inside `async`/`future` body suspends the *fiber* only and becomes cancellable via `future-cancel`. Uses `Amp\delay`.

> **Not Clojure's `delay`.** `clojure.core/delay` is a lazy-thunk wrapper, not a sleep. Phel's `delay` lives in `phel.async` (not `phel.core`) to keep the difference visible to `.cljc` portable code.

```phel
(:require phel.async :refer [delay])

(async (delay 0.1) :done)
```

### `await-all`

```phel
(await-all futures)
```

Awaits every Future and returns resolved values in order. If any Future fails, the exception propagates; others keep running to their next checkpoint.

```phel
(await-all [(async (fetch :a)) (async (fetch :b))])
```

### `await-any`

```phel
(await-any futures)
```

Returns the value of the first Future to resolve. Losing Futures are not auto-cancelled; pair with `future-cancel` when needed.

### `pmap`

```phel
(pmap f coll) (pmap f coll1 coll2 ...)
```

Concurrent `map` via fibers; results in input order. PHP fibers are cooperative on a single thread: `pmap` overlaps IO-bound work (HTTP, DB, file IO) but does **not** parallelize CPU work across cores. ClojureScript and Basilisp follow the same single-threaded model; `clojure.core/pmap` uses a thread pool.

### `future`

```phel
(future body...)
```

Wraps `body` in `Amp\Future`, returns a `Future` supporting `deref`, `realized?`, 3-arg `deref` timeouts, `future-cancel`, `future-cancelled?`, `future-done?`. Requires a fiber context (call inside `async` or an AMPHP loop).

```phel
(async
  (let [f (future (do (delay 0.1) 99))]
    (deref f 50 :timeout)))
```

### `future-cancel`

```phel
(future-cancel f)
```

Signals the Future's `DeferredCancellation` token. Pending and subsequent `deref` calls throw `Amp\CancelledException` (or return the fallback for the 3-arg form). Cooperative: the body runs until it hits a cancellation-aware checkpoint.

### `future-cancelled?`

```phel
(future-cancelled? f)
```

Returns `true` once `future-cancel` was called. Does not imply the body stopped; use `future-done?` for terminal state.

## Fiber layer reference

### `promise`

```phel
(promise)
```

Returns a new unrealized promise. Single delivery: once set, the value is frozen. `deref` suspends cooperatively from inside a fiber, or drains the scheduler from top level. Single-process; not cross-process safe.

### `deliver`

```phel
(deliver p value)
```

Delivers `value` to promise `p`. Returns `p` on first delivery, `nil` if already realized. Idempotent: subsequent calls are no-ops; stored value never changes.

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

Runs zero-arg `f` in a new fiber via the cooperative scheduler. Returns a `Future` supporting `deref`, `realized?`, `future-done?`, `future-cancel`. `f` must be zero-arg; use a closure to capture state.

### `future-fiber`

```phel
(future-fiber body...)
```

Macro over `future-call`. Write `@(future-fiber (expensive))` without an outer `async` block. No event loop, so blocking PHP calls freeze the scheduler until they return.

### `future?`

```phel
(future? x)
```

Returns `true` if `x` is a fiber-future or `Future`. Useful when receiving Futures from code that may use either layer.

## Shared primitives

`deref` dispatches to the right layer at runtime.

| Form | Behavior |
|------|----------|
| `(deref x)` / `@x` | Block until realized. Fiber path suspends cooperatively; AMPHP path awaits via the event loop |
| `(deref x timeout-ms timeout-val)` | Return `timeout-val` if not realized within `timeout-ms`. Fiber path uses a deadline poll; AMPHP path uses `Future::await` with `TimeoutCancellation` |
| `(realized? x)` | `true` once a value is available. Works for promises, fiber futures, `Future` |
| `(future-done? x)` | For fiber futures, checks `isDone`; otherwise falls back to `realized?`. Use for "terminal state including cancellation", not just "value present" |

## Error and cancellation model

- **AMPHP path**: exceptions in a `future`/`async` body surface from `await`/`deref`. Cancellation via `Amp\DeferredCancellation`; after `future-cancel`, `deref` raises `Amp\CancelledException`, and the 3-arg form returns its fallback.
- **Fiber path**: exceptions in `future-call` bodies re-raise on `deref`. `future-cancel` flips a flag checked at cooperative checkpoints; the 3-arg `deref` returns its fallback without waiting.
- **`deliver` is idempotent**: first call wins; the return value tells you whether you set it. Use for "first writer wins" handoffs without locks.

## Interop

- `->closure` converts a Phel function to a PHP `\Closure`. Many PHP libraries (AMPHP, ReactPHP) type-hint `\Closure` and reject Phel's `AbstractFn`. Wrap before passing a Phel fn.
- Bare `Amp\Future` values from AMPHP libs pass to `await`, `await-all`, `await-any` directly; no wrapping needed.
- To feed a fiber-layer result to AMPHP code, deref it inside an `async` block: `(async (use-value @(future-fiber ...)))`.

## Pitfalls

- **`future` outside an event loop**. AMPHP `future` needs a fiber context; use `future-fiber` for top-level scripts.
- **Mixing future types**. `future?` is the safe predicate; `deref`, `realized?`, `future-done?` dispatch by type.
- **CPU-bound `pmap`**. PHP fibers share one thread. CPU-heavy `f` won't speed up and may add overhead. Shell out to workers for parallelism.
- **Blocking PHP calls inside fiber futures**. `sleep`, `usleep`, synchronous `curl`, blocking socket reads freeze the scheduler. Use `delay` (AMPHP) or non-blocking IO.

## Recipes

### Producer/consumer via `promise`

```phel
(ns demo.producer)

(let [inbox (promise)]
  (future-call (fn []
                 (deliver inbox {:event :ready :at (php/time)})))
  (println "got:" @inbox))
```

Run with `./bin/phel run demo/producer.phel`.

### Fan-out, fan-in with `await-all`

```phel
(ns demo.fanout
  (:require phel.async :refer [delay]))

(defn fetch [label ms]
  (async
    (delay (php/fdiv ms 1000))
    (str label ":" ms "ms")))

(println
  (await-all [(fetch :eu 80)
              (fetch :us 40)
              (fetch :asia 60)]))
```

Wall time tracks the slowest branch, not the sum.

### Timeout race with 3-arg `deref`

```phel
(ns demo.timeout)

(let [p (promise)]
  ;; No producer wired up: the deref expires and returns the fallback.
  (println (deref p 25 :timed-out)))
;; => :timed-out
```

### Cancel on first error

```phel
(ns demo.cancel-on-error
  (:require phel.async :refer [delay]))

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

`future-cancel` is cooperative: `slow` finishes its current step before observing cancellation. Any `deref` on it now throws `Amp\CancelledException`.

## See also

- [Example: `docs/examples/11_async-concurrency.phel`](examples/11_async-concurrency.phel)
- [`phel.http-client`](../src/phel/http-client.phel) for AMPHP-based HTTP calls
