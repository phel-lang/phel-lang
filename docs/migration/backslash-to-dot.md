# Migration: Backslash Namespace Separator to Dot

Phel historically used the PHP-style backslash (`\`) as the namespace separator everywhere: `ns` forms, `:require`/`:use` clauses, fully-qualified call sites, and class FQNs. The dot (`.`) is the target form going forward. The backslash form is **deprecated** and will be removed in a future release.

Tracking issue: [phel-lang/phel-lang#2827](https://github.com/phel-lang/phel-lang/issues/2827),
which owns the removal decision. [#1567](https://github.com/phel-lang/phel-lang/issues/1567)
delivered the deprecation and this migration path, and is closed.

## Opt-in to deprecation warnings

Three equivalent ways; pick whichever fits your pipeline.

**CLI flag**, for one-off runs and CI:

```bash
vendor/bin/phel run --warn-deprecations src/app.phel
vendor/bin/phel test --warn-deprecations
```

**Environment variable**, for shell-wide sessions:

```bash
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel run src/app.phel
```

**Project config**, when every local command should opt in:

```php
return PhelConfig::forProject()
    ->withWarnDeprecations(true);
```

When enabled, the compiler emits one `E_USER_DEPRECATED` per unique `(file, symbol)` pair, so large projects do not drown in duplicates. `--warn-deprecations` is consumed by the `phel` bootstrap before Symfony's per-command parsers run, so it works with every subcommand.

The same switch turns on every other opt-in deprecation detector, notably the call-site warnings for definitions carrying `:deprecated` metadata. See [deprecated-surface.md](deprecated-surface.md) for the full list.

## What is detected today

Symbols flowing through the analyzer's `SymbolResolver` or the `ns`-form analyzer emit warnings:

- **Namespace declarations** (Phase 1b): `(ns phel\foo)` becomes `(ns phel.foo)`
- **`:require` targets** (Phase 1b, flat and `[ns :as alias]` vector forms): `(:require phel\walk)` becomes `(:require phel.walk)`
- **Fully-qualified call sites** (Phase 1a): `(phel\core/map inc xs)` becomes `(phel.core/map inc xs)`
- **Leading-backslash class FQNs** (Phase 1a): `\Phel\Lang\ExceptionInfo` becomes `Phel.Lang.ExceptionInfo`. Dot alias landed in [#1553](https://github.com/phel-lang/phel-lang/issues/1553).
- **`:use` targets**: `(:use Phel\Lang\Foo)` becomes `(:use Phel.Lang.Foo)`. The analyzer already accepted the dot form; the warning makes the migration target explicit.

## What is NOT yet detected

Tracked as follow-up sub-tasks in #2827:

- `:refer` targets inside a require (rarely contain `\` in practice)
- `load` forms (take strings, not symbols)
- Reader-macro / quoting forms that carry namespace strings as data

Migrate these positions by hand now; the dot forms already work at the language level.

## Suppression

Warnings are suppressed automatically for files under phel's bundled stdlib. User projects using the nested `src/phel` layout still emit warnings normally.

## Removal target

**Not 1.0.** The [0.49 to 1.0 upgrade guide](upgrade-0.49-to-1.0.md) lists the
separator under what is *not* changing, so `\` keeps working for projects moving
to the first stable release.

That has a consequence worth stating plainly: the
[deprecation policy](../stability.md#deprecation-policy-for-1x) removes a
deprecated form **only in a major**, so a separator still shipping at `1.0.0`
ships for the whole of `1.x`. The decision is not "now or later", it is "at 1.0
or not until 2.0", and [#2827](https://github.com/phel-lang/phel-lang/issues/2827)
owns it.

An earlier version of this page set the bar at "one full minor-release cycle
after the warning flag flips on by default". That bar cannot be met as written:
the flag is opt-in by design and stays that way
([ADR 0006](../adr/0006-one-opt-in-deprecation-channel.md)), because phel's own
suite would flood stderr otherwise. Whoever decides the removal is deciding it
for users who were never warned unless they asked to be.
