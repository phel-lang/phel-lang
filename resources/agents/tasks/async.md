# Async / fibers

`phel\async` wraps AMPHP fibers. Use for I/O concurrency (HTTP, file, sleep), not CPU work.

## Basic

```phel
(ns my-app\main
  (:require phel\async :refer [async await delay future]))

(defn fetch-slow [url]
  (delay 200)                          ; simulate latency
  (str "got " url))

(defn ^:async fetch-pair []
  (let [a (future #(fetch-slow "/a"))
        b (future #(fetch-slow "/b"))]
    [(await a) (await b)]))             ; concurrent, total ~200ms
```

`^:async defn` wraps the body in `async`; the function returns an `Amp\Future`.

## Building blocks

| Form | Returns | Purpose |
|------|---------|---------|
| `(async (fn [] body))` | `Amp\Future` | Run body in a fiber |
| `(future #(work))` | `Amp\Future` | Sugar for the common case |
| `(await fut)` | result | Block current fiber until done |
| `(delay ms)` | nil | Cooperative sleep |

## Outside an async context

`await` from synchronous code throws. Wrap callers in `async` or `^:async defn`, or call `(.await fut)` only inside a fiber.

## Combine with `^:memoize`

```phel
(defn ^:async ^:memoize fetch-user [^int id]
  (await (http-get (str "/users/" id))))
```

Cache hits skip the fiber entirely.

## Gotchas

- CPU-bound code (parsing, math) gains nothing — fibers are cooperative, single-threaded.
- `(async ...)` returns immediately; ignoring the future swallows errors. `await` or store it.
- `^:async ^{:async false}` — opt-out without removing the meta key (e.g. when bench-comparing).

## See also

- `docs/async-guide.md`
- `tasks/typed-defn.md` for combining `^:async` with `:tag` (`^"\\Amp\\Future"` return tag)
