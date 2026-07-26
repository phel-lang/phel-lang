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
(push coll x)      ; -> (conj coll x)
(put m :k v)       ; -> (assoc m :k v)
(unset m :k)       ; -> (dissoc m :k)
(put-in m ks v)    ; -> (assoc-in m ks v)
(unset-in m ks)    ; -> (dissoc-in m ks)
(values m)         ; -> (vals m)
(function? x)      ; -> (fn? x)
(hash-map? x)      ; -> (map? x)
(id a b)           ; -> (identical? a b)
```

`str-contains?` now lives in the `phel\string` namespace:

```phel
(ns my-app (:require phel\string :as s))

(str-contains? haystack needle)   ; -> (s/contains? haystack needle)
```

## Removed CLI option aliases

Two renamed options kept a deprecated alias; the aliases are now removed ([#2827](https://github.com/phel-lang/phel-lang/issues/2827)).

| Removed | Replacement |
|---------|-------------|
| `phel index --out=PATH` | `phel index --output=PATH` (`-o`) |
| `phel config --json` | `phel config --format=json` (`-f json`) |

```bash
phel index --out=index.json      # -> phel index --output=index.json
phel config --json               # -> phel config --format=json
```

Passing a removed alias now fails with Symfony's own `The "--out" option does not exist.`, so a stale CI invocation surfaces immediately rather than silently. The conventions these were aligned to are in [../internals/cli-flag-conventions.md](../internals/cli-flag-conventions.md).

## Removed REPL history migration

The REPL history file moved from `<project>/.phel-repl-history` to `<project>/.phel/repl-history` in `0.37.0`, and every REPL start since then migrated a legacy file automatically. That migration is now removed, along with the `PHEL_QUIET_MIGRATION` environment variable that silenced its notice.

A project that upgraded through any release from `0.37.0` onwards has already been migrated and needs to do nothing. A project jumping straight from before `0.37.0` keeps its old file untouched and simply starts a fresh history; move it by hand to keep it:

```bash
mkdir -p .phel && mv .phel-repl-history .phel/repl-history
```

Replace any literal `.phel-repl-history` in a `.gitignore` or CI cache key with `.phel/`.
## Removed function-parameter metadata

`^:reference` marked a by-reference fn parameter. `^:by-ref` was always the canonical spelling and the two were exactly equivalent, so the alias is removed ([#2827](https://github.com/phel-lang/phel-lang/issues/2827)).

```phel
(defn fill [^:reference buffer]       (defn fill [^:by-ref buffer]
  (php/array_push buffer 1))            (php/array_push buffer 1))
```

`^:reference` is now simply not a by-reference marker: the parameter compiles by value, and a function relying on the mutation stops propagating it to the caller. Grep for the literal `:reference` in parameter metadata, since a symbol rename will not find it.

## Removed core definitions

| Removed | Deprecated since | Replacement |
|---------|------------------|-------------|
| `set-meta!` | 0.32.0 | `with-meta` |
| `phel\test/print-summary` | 0.49.0 | react to the `:summary` event |

`set-meta!` was a thin alias with the same arguments and return value:

```phel
(set-meta! [] {:a 1})   ; -> (with-meta [] {:a 1})
```

`run-tests` already emits the `:summary` event at the end of a run, so calling `print-summary` yourself double-reported:

```phel
(t/run-tests {} 'my-app.core-test)    (t/run-tests {} 'my-app.core-test)
(t/print-summary)                     ;; nothing else needed
```

A custom reporter reacts to the `:summary` event instead of triggering it. A test harness that needs a summary for stats it assembled itself builds the event from the public `get-stats` snapshot and hands it to the reporter; `phel.test` keeps its own builder private on purpose, so the event shape stays free to change.

## Still deprecated (not removed)

The `warn-deprecations` infrastructure stays, since it still serves live deprecations such as the deprecated reader syntax and the `\` namespace separator (see [backslash-to-dot.md](backslash-to-dot.md)). `DeprecatedDefinitionWarner` stays too: it is a general facility that project code uses for its own `:deprecated` definitions.

[deprecated-surface.md](deprecated-surface.md) maps the whole set of deprecations that are still shipped, with a before/after for each.
