# Migration: The Currently Deprecated Surface

Everything below still works today. Everything below is scheduled for removal in
a future major release. This page is the single map of that surface: what the
item is, what replaces it, and the mechanical before/after.

Two related pages cover migrations that already finished:

- [Removed deprecated core functions](removed-deprecated-core-fns.md)
- [Backslash namespace separator to dot](backslash-to-dot.md) (deep dive on the
  one item below with its own tracking issue)

## How a deprecation announces itself

Everything the **compiler** knows about, syntax and definitions alike, goes
through one switch: `Phel\Compiler\Domain\Deprecation\DeprecationWarnings`. It
is off by default and reports when you ask for it.

CLI-option deprecations are the one thing outside it: they always print a
one-line notice on stderr through `Phel\Shared\Console\DeprecatedOptionWarner`,
because a renamed flag is a single unmissable event rather than something
scattered through your source.

Turn compiler deprecation warnings on with any of:

```bash
vendor/bin/phel run --warn-deprecations src/main.phel
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel test
```

```php
return PhelConfig::forProject()->withWarnDeprecations(true);
```

Uses inside phel's own bundled stdlib are suppressed, so the output lists only
code you own. Notices about a *definition* or a `\`-separated symbol are
deduplicated per `(file, subject)` pair, since one name can recur hundreds of
times in a file. Syntax notices are not deduplicated: each occurrence is a
separate edit you have to make.

Deprecation messages never name a concrete removal version on purpose: the
release such a message promises inevitably ships and the text goes stale.

## Namespace separator

`\` still parses as a namespace separator; `.` is the target form.

```phel
(ns my-app\core                       (ns my-app.core
  (:require phel\string :as s))         (:require phel.string :as s))

(phel\core/map inc xs)                (phel.core/map inc xs)
\Phel\Lang\ExceptionInfo              Phel.Lang.ExceptionInfo
```

Full detail, including what is and is not detected today, is in
[backslash-to-dot.md](backslash-to-dot.md). Tracked in
[#1567](https://github.com/phel-lang/phel-lang/issues/1567).

## Function-parameter metadata

`^:reference` marks a by-reference parameter. `^:by-ref` is the canonical
spelling and the two are exactly equivalent.

```phel
(defn fill [^:reference buffer]       (defn fill [^:by-ref buffer]
  (php/array_push buffer 1))            (php/array_push buffer 1))
```

## Core library

### `set-meta!`

A thin alias for `with-meta`, deprecated since `0.32.0`. Same arguments, same
return value.

```phel
(set-meta! [] {:a 1})                 (with-meta [] {:a 1})
```

`(doc set-meta!)` and `phel doc set-meta!` also print a `DEPRECATED:` line, with
or without the flag.

### `phel\test/print-summary`

`run-tests` already emits the `:summary` event at the end of a run, so calling
`print-summary` yourself double-reports. Custom reporters should react to the
`:summary` event instead of triggering it.

```phel
(t/run-tests {} 'my-app.core-test)    (t/run-tests {} 'my-app.core-test)
(t/print-summary)                     ;; nothing else needed
```

If a test *harness* needs a summary for stats it assembled itself, keep reading
the stats atom and fan out its own event rather than calling `print-summary`.

`(doc print-summary)` also prints a `DEPRECATED:` line, with or without the flag.

## Deprecating your own definitions

The mechanism is not phel-specific. Any `def`/`defn` carrying
`:deprecated` metadata warns at every call site once warnings are enabled, so
a library can put its consumers on the same migration path:

```phel
(defn old-parse
  "Parses a config string."
  {:deprecated "1.4.0"
   :superseded-by "parse-config"}
  [s]
  (parse-config s))
```

`:deprecated` accepts a version string (rendered as "since 1.4.0"), any other
string (rendered verbatim as the reason), or `true`. `:superseded-by` is
optional and names the replacement. Both keys also show up in `phel doc`.
