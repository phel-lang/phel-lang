# Migration: Removed Long-Deprecated Core Functions

Everything here is **removed**. The step-by-step upgrade path is
[upgrade-0.49-to-1.0.md](upgrade-0.49-to-1.0.md); this page is the full record.

## Core aliases ([#2784](https://github.com/phel-lang/phel-lang/issues/2784))

Each was a thin alias, so the replacement takes the same arguments and behaves
the same way.

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
| `str-contains?` | long-deprecated | `phel.string/contains?` |
| `set-meta!` | 0.32.0 | `with-meta` |
| `phel.test/print-summary` | 0.49.0 | react to the `:summary` event |

`str-contains?` moved namespace, so it needs a require:

```phel
(ns my-app (:require phel.string :as s))

(str-contains? haystack needle)   ; -> (s/contains? haystack needle)
```

`run-tests` already emits `:summary` at the end of a run, so calling
`print-summary` double-reported. A custom reporter reacts to the event instead of
triggering it. A harness needing a summary for stats it assembled itself builds
the event from the public `get-stats` snapshot; `phel.test` keeps its own builder
private so the event shape stays free to change.

## Reader syntax ([#2827](https://github.com/phel-lang/phel-lang/issues/2827))

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

(map |(inc $) xs)                     (map #(inc %) xs)
(map |(+ $1 $2) xs ys)                (map #(+ %1 %2) xs ys)

`(list ,x ,@xs)                       `(list ~x ~@xs)
`(let [v$ ,x] (+ v$ v$))              `(let [v# ~x] (+ v# v#))
```

`#`, `#|` and `|(` are no longer tokens, so a file using them fails to lex or
reports an unresolvable symbol `|`.

**`,` is the exception and the one to grep for.** A comma is plain whitespace
everywhere now, syntax-quote included: `` `(foo ,x) `` still parses and quotes
`x` instead of unquoting it, so a macro keeps compiling and starts producing the
wrong expansion. Phel's own stdlib had three (`aset`, `aset-in`,
`router/compiled-router`).

```bash
grep -rnE ",[A-Za-z0-9_(\[{'\`~@:*+-]" --include='*.phel' src/ tests/
```

A comma between map pairs (`{:a 1, :b 2}`) is followed by a space and is
unaffected. `phel format` preserves commas and never inserts them.

Only the auto-gensym meaning of a trailing `$` is gone. `$` is still the return
value inside an `fn` `:post` condition, and an ordinary character in a name.

## Function-parameter metadata ([#2827](https://github.com/phel-lang/phel-lang/issues/2827))

`^:reference` and `^:by-ref` marked the same thing; the alias is removed.

```phel
(defn fill [^:reference buffer]       (defn fill [^:by-ref buffer]
  (php/array_push buffer 1))            (php/array_push buffer 1))
```

`^:reference` is no longer a by-reference marker: the parameter compiles by
value and a function relying on the mutation stops propagating it. Grep for the
literal `:reference`, since a symbol rename will not find it.

## CLI option aliases ([#2827](https://github.com/phel-lang/phel-lang/issues/2827))

| Removed | Replacement |
|---------|-------------|
| `phel index --out=PATH` | `phel index --output=PATH` (`-o`) |
| `phel config --json` | `phel config --format=json` (`-f json`) |

A removed alias now fails with Symfony's `The "--out" option does not exist.`, so
a stale CI invocation surfaces immediately. Conventions:
[cli-flag-conventions.md](../internals/cli-flag-conventions.md).

## REPL history migration

History moved from `<project>/.phel-repl-history` to `<project>/.phel/repl-history`
in `0.37.0`, and every REPL start since migrated a legacy file automatically. That
migration is removed, along with `PHEL_QUIET_MIGRATION`.

A project that upgraded through any release from `0.37.0` on is already migrated.
One jumping straight from before `0.37.0` starts a fresh history; move it by hand
to keep it, and replace any literal `.phel-repl-history` in `.gitignore` or a CI
cache key with `.phel/`:

```bash
mkdir -p .phel && mv .phel-repl-history .phel/repl-history
```

## Still deprecated

The `warn-deprecations` infrastructure stays: it serves live deprecations such as
the `\` namespace separator, and `DeprecatedDefinitionWarner` is a general
facility project code uses for its own `:deprecated` definitions.
[deprecated-surface.md](deprecated-surface.md) maps everything still shipped.
