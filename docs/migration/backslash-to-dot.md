# Migration: backslash namespace separator → dot

Phel historically uses the PHP-style backslash (`\`) as the namespace
separator in every position: `ns` forms, `:require`/`:use` clauses,
fully-qualified call sites, and class FQNs. Clojure — and therefore
`.cljc` code shared with sibling dialects — uses the dot (`.`). For
long-term Clojure compatibility, the backslash form is being
**deprecated** and will be removed in a future release.

See tracking issue:
[phel-lang/phel-lang#1567](https://github.com/phel-lang/phel-lang/issues/1567).

## Opt-in to deprecation warnings

Set `PHEL_WARN_DEPRECATIONS=1` before running `phel`:

```bash
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel run src/app.phel
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel test
```

When enabled, the compiler emits one `E_USER_DEPRECATED` per unique
`(file, symbol)` pair so large projects do not drown in duplicates.

## What the current (Phase 1a) PR detects

Only symbols flowing through the analyzer's `SymbolResolver` emit
warnings — that covers:

- **Fully-qualified call sites**: `(phel\core/map inc xs)` → use
  `(phel.core/map inc xs)`
- **Leading-backslash class FQNs**: `\Phel\Lang\ExInfoException` →
  use `Phel.Lang.ExInfoException` (the dot alias landed in
  [#1553](https://github.com/phel-lang/phel-lang/issues/1553))

## What is NOT yet detected (Phase 1b+)

Tracked as follow-up sub-tasks in #1567:

- `ns` declarations: `(ns phel\foo)` → `(ns phel.foo)`
- `:require` / `:use` / `:refer` / `:as` clauses
- `load` forms
- Reader-macro / quoting forms that carry namespace strings

Until those phases ship, running with `PHEL_WARN_DEPRECATIONS=1` only
surfaces the call-site uses. It is safe to migrate the non-detected
positions by hand now — the new dot forms already work.

## Suppression

Warnings are suppressed automatically for files under phel's bundled
stdlib (`.../src/phel/...`). The stdlib itself is written in backslash
form today and will be rewritten to dot form before the backslash form
is removed.

## Removal target

TBD — tracked in #1567. At minimum one full minor-release cycle after
the warning flag flips on by default.
