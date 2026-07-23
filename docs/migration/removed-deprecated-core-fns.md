# Migration: Removed Long-Deprecated Core Functions

The core functions below were deprecated for many releases and are now **removed** ([#2784](https://github.com/phel-lang/phel-lang/issues/2784)). Each was a thin alias, so replace every call site with the canonical function; arguments and behavior are unchanged.

| Removed | Deprecated since | Replacement |
|---------|------------------|-------------|
| `push` | 0.25.0 | `conj` |
| `put` | 0.25.0 | `assoc` |
| `unset` | 0.25.0 | `dissoc` |
| `put-in` | 0.25.0 | `assoc-in` |
| `unset-in` | 0.25.0 | `dissoc-in` |
| `values` | 0.32.0 | `vals` |
| `function?` | 0.32.0 | `fn?` |
| `hash-map?` | 0.32.0 | `map?` |
| `id` | 0.32.0 | `identical?` |
| `str-contains?` | long-deprecated | `phel\string\contains?` |

## How to migrate

The replacement keeps the same signature:

```phel
(push coll x)      # -> (conj coll x)
(put m :k v)       # -> (assoc m :k v)
(unset m :k)       # -> (dissoc m :k)
(put-in m ks v)    # -> (assoc-in m ks v)
(unset-in m ks)    # -> (dissoc-in m ks)
(values m)         # -> (vals m)
(function? x)      # -> (fn? x)
(hash-map? x)      # -> (map? x)
(id a b)           # -> (identical? a b)
```

`str-contains?` now lives in the `phel\string` namespace:

```phel
(ns my-app (:require phel\string :as s))

(str-contains? haystack needle)   # -> (s/contains? haystack needle)
```

## Still deprecated (not removed)

`set-meta!` (use `with-meta`) remains available but deprecated; it is intentionally out of scope for this removal. The `warn-deprecations` infrastructure also stays, since it still serves live deprecations such as the `\` namespace separator (see [backslash-to-dot.md](backslash-to-dot.md)).
