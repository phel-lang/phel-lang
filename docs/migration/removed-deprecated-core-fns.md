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

## Removed reader syntax

Six spellings are removed ([#2827](https://github.com/phel-lang/phel-lang/issues/2827)).

| Removed | Replacement |
|---|---|
| `#\| ... \|#` multiline comment | `;;` line comments, or `#_` to skip one form |
| `#` line comment | `;` (trailing) / `;;` (whole line) |
| `\|(...)` short fn, with `$`/`$1` params | `#(...)`, with `%`/`%1` params |
| `,` unquote | `~` |
| `,@` unquote-splicing | `~@` |
| `foo$` auto-gensym | `foo#` |

```phel
# old line comment                    ;; new line comment

#|
  old block comment
|#                                    ;; new block comment, one line at a time

(map |(inc $) xs)                     (map #(inc %) xs)
(map |(+ $1 $2) xs ys)                (map #(+ %1 %2) xs ys)

`(list ,x ,@xs)                       `(list ~x ~@xs)
`(let [v$ ,x] (+ v$ v$))              `(let [v# ~x] (+ v# v#))
```

### Most of these now fail loudly

`#`, `#|` and `|(` are simply not tokens any more, so a file still using them fails to lex or reports an unresolvable symbol `|`. You will know immediately.

**`,` is the exception, and it is the one to grep for.** A comma is now plain whitespace *everywhere*, syntax-quote included. `` `(foo ,x) `` still parses; it just quotes the symbol `x` instead of unquoting it, so a macro keeps compiling and starts producing the wrong expansion. Phel's own stdlib had three of these (`aset`, `aset-in` and `router/compiled-router`), which is a fair warning about how easy they are to miss.

Grep for a comma directly followed by the start of a form:

```bash
grep -rnE ",[A-Za-z0-9_(\[{'\`~@:*+-]" --include='*.phel' src/ tests/
```

A comma between map pairs (`{:a 1, :b 2}`) is followed by a space and is unaffected. `phel format` keeps preserving commas and still never inserts them.

### `$` is still a symbol

Only the *auto-gensym* meaning of a trailing `$` is gone. `$` remains the return value inside an `fn` `:post` condition, and a `$` anywhere in a name is an ordinary character.

## Still deprecated (not removed)

`set-meta!` (use `with-meta`) remains available but deprecated; it is intentionally out of scope for this removal. The `warn-deprecations` infrastructure also stays, since it still serves live deprecations such as the `\` namespace separator (see [backslash-to-dot.md](backslash-to-dot.md)).

[deprecated-surface.md](deprecated-surface.md) maps the whole set of deprecations that are still shipped, with a before/after for each.
