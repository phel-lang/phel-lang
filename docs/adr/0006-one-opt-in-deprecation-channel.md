# ADR 0006: One opt-in channel for compiler deprecations

- **Status**: Accepted (recorded retroactively; shaped by #2783 and #2827)
- **Date**: 2026-07-29

## Context

Phel deprecates in several dimensions: reader syntax (`,` as unquote, `#`
comments), namespace spelling, special forms (`php/->`, `set-var`), and
definitions carrying `:deprecated` metadata. Left alone, each detector grows its
own flag, message shape and idea of where to report.

Two failures made the cost concrete. A notice raised during emission landed inside
the emitter's `ob_start()` buffer and was spliced into generated PHP, failing the
compile with `syntax error, unexpected token ":"`. Separately, a `\` inside a
macro expansion was reported against the *call site*, telling users to fix a file
that contained nothing wrong.

CLI flags are genuinely different: a renamed flag is one event at invocation time,
and hiding it behind an opt-in means nobody learns the flag moved.

## Decision

Everything the compiler knows about reports through one switch,
`Phel\Compiler\Domain\Deprecation\DeprecationWarnings`, **off by default**,
enabled by `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS=1`, or the
`warn-deprecations` config key.

It owns five things so no detector re-implements them: the flag, the
bundled-stdlib suppression, the `(file, subject)` dedup, macro-expansion
attribution, and the syntax message shape. Detectors detect and nothing else.

Four rules follow:

1. **Never `@`-suppress a notice.** That hides it unconditionally, so
   `--warn-deprecations` prints nothing. Use `warn()`, `warnForSource()`,
   `warnOnceForSource()`, `warnSyntax()`.
2. **Route through `Domain/Diagnostic/ErrorNotice::raise()`,** which pins
   `display_errors` to stderr for the `trigger_error()` call, so a diagnostic
   cannot be captured into generated output.
3. **No concrete removal version in a message.** The named release ships and the
   text goes stale. The tracking issue carries the schedule.
4. **Attribute by expansion origin** (`warnOnceAtOrigin()`): an expansion reports
   against the macro's file with the call site appended, a stdlib origin is
   suppressed, an unknown origin stays silent rather than misattributed.

CLI-option deprecations are the one exception and always print on stderr through
`Phel\Shared\Console\DeprecatedOptionWarner`.

Every live deprecation also appears in
[the deprecated surface map](../migration/deprecated-surface.md) with its
replacement and a before/after, moving to the removed page once gone.

## Consequences

Off by default means a user who never passes the flag never sees a deprecation.
The trade: warnings on by default are noisy for people who cannot act yet (a
dependency's Phel code, generated files, a codebase mid-migration), and noisy
warnings get globally silenced, which loses the channel permanently.

Dedup differs by kind. A deprecated definition or `\`-separated symbol dedups per
`(file, subject)`, since one name recurs hundreds of times. Syntax notices do not:
each occurrence is a separate edit.

The stdlib suppression means notices only name code the user can edit. Paths are
`realpath`-normalized and memoized, so a stdlib file reached through a relative
prefix still matches.

Any `def`/`defn` with `:deprecated` metadata warns at its call sites through the
same switch, so libraries get the mechanism rather than a phel-specific one.

## Enforcement

- `tests/php/Unit/Compiler/Deprecation/`, including `SupersededFormDeprecatorTest`
- `SupersededFormDeprecationTest` and `MacroExpansionDeprecationTest`
  (integration, attribution)
- `LexerTest::VERSION_REFERENCE` guards rule 3
- `LanguageSurfaceSpecTest` checks the spec's deprecated-forms table against
  `SupersededFormDeprecator`
- PHPUnit narrows `error_reporting`; a test asserting on a notice must widen it to
  `E_ALL` or it passes for the wrong reason

## Alternatives considered

- **On by default.** Revisit only with a way to scope notices to first-party
  source.
- **A flag per deprecation family.** Guarantees the least-known family is never
  enabled.
- **Naming the removal version.** Rejected in #2783 after the text went stale.

## See also

- [The currently deprecated surface](../migration/deprecated-surface.md)
- [Stability policy](../stability.md#deprecation-policy-for-1x)
- `src/php/Compiler/CLAUDE.md`: detector table and attribution rules
- [ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md),
  [ADR 0008](0008-dot-namespace-separator.md)
