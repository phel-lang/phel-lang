# Migration: Backslash Namespace Separator to Dot

Phel historically used the PHP-style backslash (`\`) as the namespace separator everywhere: `ns` forms, `:require`/`:use` clauses, fully-qualified call sites, and class FQNs. The dot (`.`) is the target form going forward. The backslash form is **deprecated** and will be removed in a future release.

Tracking issue: [phel-lang/phel-lang#2827](https://github.com/phel-lang/phel-lang/issues/2827),
which owns the removal decision. [#1567](https://github.com/phel-lang/phel-lang/issues/1567)
delivered the deprecation and this migration path, and is closed.

## This one warns without being asked

The separator is the single deprecation that announces by default
([ADR 0014](../adr/0014-announce-the-separator-deprecation.md)): it is scheduled
for removal at the next major, and a notice shown only to people who already knew
to ask for it does not give anyone time to act. Every other deprecation stays
opt-in.

Two rules keep it quiet enough to live with. A file naming one `\`-separated
symbol a hundred times reports **once** per `(file, symbol)`, and code under a
`vendor/` directory is never reported: a dependency's spelling is its author's to
fix.

## Opt-in to the rest of the deprecations

Three equivalent ways; pick whichever fits your pipeline. These turn on every
*other* detector, and the separator reports either way.

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
- **Leading-backslash class FQNs** (Phase 1a): `\Phel\Lang\ExceptionInfo` becomes `Phel.Lang.ExceptionInfo`. Dot alias landed in [#1553](https://github.com/phel-lang/phel-lang/issues/1553).
- **`:use` targets**: `(:use Phel\Lang\Foo)` becomes `(:use Phel.Lang.Foo)`. The analyzer already accepted the dot form; the warning makes the migration target explicit.

## What is NOT yet detected

A **fully-qualified call site** (`(phel\string/join "," xs)`) is the notable one:
it is not reported, with or without the flag
([#2931](https://github.com/phel-lang/phel-lang/issues/2931)). An earlier version
of this page listed it as detected, which it has never been.

Tracked as follow-up sub-tasks in #2827:

- `:refer` targets inside a require (rarely contain `\` in practice)
- `load` forms (take strings, not symbols)
- Reader-macro / quoting forms that carry namespace strings as data

Migrate these positions by hand now; the dot forms already work at the language level.

## Suppression

Warnings are suppressed automatically for files under phel's bundled stdlib, and
for anything under a `vendor/` directory: a dependency's spelling is its author's
to fix, not yours. User projects using the nested `src/phel` layout still emit
warnings normally, since the stdlib rule anchors on phel's own package path.

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
after the warning flag flips on by default", which could not be met while the
flag gated this notice: the switch is opt-in by design
([ADR 0006](../adr/0006-one-opt-in-deprecation-channel.md)), so the cycle could
never start and a removal would have landed on users who were never warned.

That is now settled the other way. The separator announces without the flag from
`1.0.0` ([ADR 0014](../adr/0014-announce-the-separator-deprecation.md)), so the
notice period is running for everyone, and the removal at the next major follows
a warning people actually saw.
