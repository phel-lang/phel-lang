# Memoize

Opt-in caching via `defn` metadata. Cache key is the full positional arg vector.

## Forms

```phel
(defn ^:memoize slow-hash [s] (php/sha1 (str s)))

(defn ^{:memoize-lru 256} fib [^int n]
  (if (< n 2) n (+ (fib (- n 1)) (fib (- n 2)))))

(defn ^{:memoize false} sometimes-cached [...] ...)   ; opt-out flag
```

| Meta | Cache | Bound |
|------|-------|-------|
| `^:memoize` | unbounded | grows for life of process |
| `^{:memoize-lru N}` | LRU | last `N` distinct arg vectors |
| `^{:memoize false}` | none | escape hatch with meta intact |

Combine with `:tag` for static type checks + JIT hints:

```phel
(defn ^{:memoize-lru 1024 :tag "int"} parse-id [^string s]
  (php/intval s))
```

## When NOT to memoize

- Side-effecting fn (the second call returns the first call's stale result; effects skipped).
- Args contain mutable refs (atoms, PHP objects) — identity, not value, is the key.
- Hot path with low cache-hit ratio — pure overhead.

## Inspect

```phel
(phel doc memoize)
(phel doc memoize-lru)
```

Manual cache reset isn't part of the public API; recreate the var by re-`def`/reload to clear.

## See also

- `tasks/typed-defn.md` for combining `:tag` + `:memoize`
- `tasks/async.md` for `^:async ^:memoize` patterns
