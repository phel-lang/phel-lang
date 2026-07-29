# Migration: The Currently Deprecated Surface

Everything below still works today. Everything below is scheduled for removal in
a future major release. This page is the single map of that surface: what the
item is, what replaces it, and the mechanical before/after.

Three related pages cover migrations that already finished, or are about to:

- [Upgrading 0.49 to 1.0](upgrade-0.49-to-1.0.md): the step-by-step version of everything removed for the major
- [Removed deprecated core functions](removed-deprecated-core-fns.md)
- [Backslash namespace separator to dot](backslash-to-dot.md) (deep dive on the
  one item below with its own tracking issue)

## How a deprecation announces itself

Everything the **compiler** knows about, syntax and definitions alike, goes
through one switch: `Phel\Compiler\Domain\Deprecation\DeprecationWarnings`. It
is off by default and reports when you ask for it.

CLI-option deprecations are the one thing outside it: a renamed flag prints a
one-line notice on stderr unconditionally, because it is a single unmissable
event rather than something scattered through your source. No rename is in
flight today, so there is no shared helper for it; see
[cli-flag-conventions.md](../internals/cli-flag-conventions.md#renaming-an-option).

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

## Redundant interop forms: `php/new`, `php/->`, `php/::`

`php/` marks host access: reaching a PHP function, a PHP array or a PHP
reference, none of which Phel has a word for. It is not a second spelling for
something Phel already says the Clojure way. These three were the second
spelling, so they are deprecated and the Clojure-style form is now the only
one. Everything on the right has worked for a long time; what changed is that
the last positions needing a `php/*` fallback were closed
([#2881](https://github.com/phel-lang/phel-lang/issues/2881),
[#2883](https://github.com/phel-lang/phel-lang/issues/2883),
[#2887](https://github.com/phel-lang/phel-lang/issues/2887)).

```phel
(php/new \DateTime "2024-03-10")      (new \DateTime "2024-03-10")
                                      (\DateTime. "2024-03-10")

(php/-> d (format "Y"))               (.format d "Y")
(php/-> obj prop)                     (.-prop obj)

(php/:: \DateTime (createFromFormat   (\DateTime/createFromFormat
        "Y-m-d" "2024-03-10"))          "Y-m-d" "2024-03-10")
(php/:: \Foo BAR)                     \Foo/BAR
```

Chaining needs nothing special: `(-> d (.modify "+1 day") (.format "Y-m-d"))`
threads with plain `->`, methods and properties alike.

**Writing a macro** whose method or class name is computed at expansion time is
the one case that used to force `php/->`. Build the head symbol instead:

```phel
`(~(symbol (str "." method-name)) ~receiver ~@args)   ; (.method recv args)
`(~(symbol "\\Phel" static-name))                     ; (\Phel/staticName)
```

The mutating forms are unaffected and stay: `php/aget`, `php/aset`,
`php/apush`, `php/aunset`, `php/oset`, `php/ref` and `php/callable` each reach
a PHP capability with no Clojure counterpart. `phel.core` spells the common
ones at top level (`aget`, `aset`, `aclone`, `alength`, `set!`).

Tracked in [#2877](https://github.com/phel-lang/phel-lang/issues/2877).

## `set-var`

`set-var` writes a var's **root**, which Clojure calls `alter-var-root`. Its
name reads like Clojure's `set!`, which does the opposite (it assigns the
current thread-local binding and throws when there is none) — and since
[#2905](https://github.com/phel-lang/phel-lang/pull/2905) Phel has that `set!`
too, so the misleading name now sits next to the thing it is mistaken for.

```phel
(set-var *x* 3)                       (alter-var-root #'*x* (constantly 3))
```

`alter-var-root` takes a var and a **function**, so a plain value goes through
`constantly`. To assign only the current `binding` frame, that is `(set! *x* 3)`.

`binding` and `with-redefs` still expand into `set-var`: an emitted `set-var`
inside an open frame is what records the rebinding. Removing the public form
therefore needs a non-public primitive to take that over first.

Tracked in [#2888](https://github.com/phel-lang/phel-lang/issues/2888).

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
