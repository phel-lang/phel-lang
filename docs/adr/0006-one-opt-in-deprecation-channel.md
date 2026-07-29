# ADR 0006: One opt-in channel for compiler deprecations

- **Status**: Accepted (recorded retroactively; shaped by #2783 and #2827)
- **Date**: 2026-07-29

## Context

Phel deprecates in several dimensions at once: reader syntax (`,` as unquote, `#`
comments), namespace spelling (`\` versus `.`), special forms (`php/->`,
`set-var`), and ordinary definitions carrying `:deprecated` metadata. Left to
themselves, each detector grows its own flag, its own message shape, and its own
idea of where to report.

Two failures made the cost concrete.

A notice raised during emission landed inside the emitter's `ob_start()` buffer
and was spliced into the generated PHP, failing the compile with
`syntax error, unexpected token ":"`. A deprecation notice had corrupted a build.

Separately, a `\` inside a macro's expansion was reported against the *call site*,
so users saw warnings pointing at their own file for syntax written in
`src/phel/core/lazy.phel`. Being told to fix a file that contains nothing wrong is
worse than not being warned.

There is also a class of deprecation that is genuinely different. A renamed CLI
flag is one unmissable event at invocation time, not something scattered through
source, and suppressing it until somebody opts in would mean nobody ever learns
the flag moved.

## Decision

Everything the compiler knows about, syntax and definitions alike, reports through
one switch: `Phel\Compiler\Domain\Deprecation\DeprecationWarnings`. It is **off by
default** and enabled by `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS=1`, or the
`warn-deprecations` config key.

That class owns five things so no detector re-implements them: the enabled flag,
the bundled-stdlib suppression, the `(file, subject)` dedup, the macro-expansion
attribution, and the syntax message shape. Detectors detect and nothing else. They
hold no flag, no dedup table and no emitter.

Four rules follow from it:

1. **Never `@`-suppress a notice.** That hides it unconditionally, so a
   `--warn-deprecations` run prints nothing. Call `warn()`, `warnForSource()`,
   `warnOnceForSource()` or `warnSyntax()`.
2. **Notices route through `Domain/Diagnostic/ErrorNotice::raise()`,** which pins
   `display_errors` to stderr for the duration of the `trigger_error()` call, so a
   diagnostic can never be captured into generated output again.
3. **No concrete removal version in any message.** A named release ships and the
   text goes stale. The tracking issue carries the schedule.
4. **Attribution is by expansion origin,** via `warnOnceAtOrigin()`: an expansion
   is reported against the macro's file with the call site appended, a
   bundled-stdlib origin is suppressed, and an unknown origin stays silent rather
   than being misattributed.

CLI-option deprecations are the single documented exception. They always print on
stderr through `Phel\Shared\Console\DeprecatedOptionWarner`.

Every live deprecation also appears in
[the deprecated surface map](../migration/deprecated-surface.md) with its
replacement and a mechanical before/after. When it is removed it moves to the
removed page, so the "still deprecated" page shrinks to exactly what still ships.

## Consequences

Off by default is the trade that gets argued most. It means a user who never
passes the flag never sees a deprecation, and the migration only starts when they
look. The alternative was weighed against a constraint that is easy to
underestimate: warnings on by default make every upgrade noisy for people who
cannot act on them yet (a
dependency's Phel code, a generated file, a large codebase mid-migration), and
noisy-by-default warnings get globally silenced, which loses the channel
permanently. An opt-in channel that users trust beats an opt-out one they mute.

The dedup rules differ by kind on purpose. A deprecated definition or a
`\`-separated symbol dedups per `(file, subject)`, since one name can recur
hundreds of times. Syntax notices do not dedup: each occurrence is a separate edit.

The stdlib suppression means notices only ever name code the user can edit. Paths
are `realpath`-normalized and memoized so a stdlib file reached through a relative
prefix still matches.

Any `def`/`defn` carrying `:deprecated` metadata warns at its call sites under the
same switch, so a library gets the same mechanism as the compiler rather than a
phel-specific one.

## Enforcement

- `tests/php/Unit/Compiler/Deprecation/`, including
  `SupersededFormDeprecatorTest.php`
- `tests/php/Integration/Compiler/SupersededFormDeprecationTest.php` and
  `MacroExpansionDeprecationTest.php` (attribution)
- `LexerTest::VERSION_REFERENCE` guards rule 3
- `LanguageSurfaceSpecTest` checks the deprecated-forms table in the spec against
  `SupersededFormDeprecator`, so the page and the compiler cannot disagree about
  which forms warn
- Note that PHPUnit narrows `error_reporting`, so a test asserting on a notice has
  to widen it to `E_ALL` or it passes for the wrong reason

## Alternatives considered

- **Warnings on by default.** Rejected: see above. Revisit only with a way to
  scope them to first-party source.
- **A flag per deprecation family.** Rejected: it moves the decision to the user
  and guarantees that the least-known family is never enabled.
- **Naming the removal version in the message.** Rejected in #2783 after the text
  went stale exactly as predicted.

## See also

- [The currently deprecated surface](../migration/deprecated-surface.md)
- [Stability policy: deprecation policy for 1.x](../stability.md#deprecation-policy-for-1x)
- `src/php/Compiler/CLAUDE.md`: the detector table and the attribution rules
- [ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md),
  [ADR 0008](0008-dot-namespace-separator.md): two deprecations that use it
