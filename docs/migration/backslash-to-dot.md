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

Two equivalent ways to turn the warnings on — pick whichever fits
your pipeline:

**CLI flag** (recommended for one-off runs and CI configs):

```bash
vendor/bin/phel run --warn-deprecations src/app.phel
vendor/bin/phel test --warn-deprecations
```

**Environment variable** (recommended for shell-wide sessions):

```bash
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel run src/app.phel
```

**Project config** (recommended when every local command should opt in):

```php
return PhelConfig::forProject()
    ->setWarnDeprecations(true);
```

When enabled, the compiler emits one `E_USER_DEPRECATED` per unique
`(file, symbol)` pair so large projects do not drown in duplicates.
The `--warn-deprecations` flag is consumed by the `phel` bootstrap
before Symfony's per-command parsers run, so it works with every
subcommand.

## What is detected today

Symbols flowing through the analyzer's `SymbolResolver` **or** the
`ns`-form analyzer emit warnings:

- **Namespace declarations** (Phase 1b): `(ns phel\foo)` → use
  `(ns phel.foo)`
- **`:require` targets** (Phase 1b, flat and `[ns :as alias]` vector
  forms): `(:require phel\walk)` → `(:require phel.walk)`
- **Fully-qualified call sites** (Phase 1a): `(phel\core/map inc xs)`
  → `(phel.core/map inc xs)`
- **Leading-backslash class FQNs** (Phase 1a):
  `\Phel\Lang\ExInfoException` → `Phel.Lang.ExInfoException` (the dot
  alias landed in [#1553](https://github.com/phel-lang/phel-lang/issues/1553))

- **`:use` targets**: `(:use Phel\Lang\Foo)` → `(:use Phel.Lang.Foo)`.
  The analyzer already accepted the dot form; the warning just makes
  the migration target explicit.

## What is NOT yet detected

Tracked as follow-up sub-tasks in #1567:

- `:refer` targets inside a require (rarely contain `\` in practice)
- `load` forms (take strings, not symbols)
- Reader-macro / quoting forms that carry namespace strings as data

It is safe to migrate the non-detected positions by hand now — the
new dot forms already work at the language level.

## Suppression

Warnings are suppressed automatically for files under phel's bundled
stdlib. User projects that use the nested `src/phel` layout still emit
warnings normally.

## Removal target

TBD — tracked in #1567. At minimum one full minor-release cycle after
the warning flag flips on by default.
