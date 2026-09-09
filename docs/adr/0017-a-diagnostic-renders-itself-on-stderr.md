# ADR 0017: A compiler diagnostic renders itself on stderr

- **Status**: Accepted
- **Date**: 2026-09-09
- **Amends**: [0006](0006-one-opt-in-deprecation-channel.md) — its decision point
  2 named `trigger_error()` as the rendering mechanism. One channel, one owner,
  and the `display_errors` guard all stand; only who writes the line changes.

## Context

`ErrorNotice::raise()` handed every notice to `trigger_error()` and let PHP print
it. One deprecated `\` in a `ns` form therefore reached the user as four lines
([#3262](https://github.com/phel-lang/phel-lang/issues/3262)): PHP renders a
notice twice when both `log_errors` and `display_errors` are on, and the same
symbol was reported once by the namespace scan and once by the compile.

Every one of those lines ended `in .../Compiler/Domain/Diagnostic/ErrorNotice.php
on line 56`. PHP appends the file and line of the call that raised the notice, and
that call is an `@internal` class the user cannot open, sitting where a Phel
diagnostic names the user's own source.

[ADR 0014](0014-announce-the-separator-deprecation.md) made this notice announce
by default so the migration happens inside `1.x`, which makes it the diagnostic a
`1.0` user sees most.

## Decision

`ErrorNotice::raise()` writes the diagnostic itself, one line to stderr, prefixed
`deprecated: ` or `warning: ` by channel. PHP never renders a Phel diagnostic, so
it can neither double it nor stamp an internal file onto it.

Two rules keep the change to rendering only:

1. **A userland `set_error_handler` still gets a real `E_USER_*`.** A handler was
   installed to collect PHP notices, and a Phel deprecation is one. That path
   keeps the `display_errors` redirect from ADR 0006: a handler that declines the
   notice hands it back to PHP, whose CLI default display is STDOUT, which the
   emitter's `ob_start()` would splice into the generated code (#2827).
2. **A direct write honours the same silencing PHP would have.** `error_reporting`
   masking the level, or both `display_errors` and `log_errors` being off, stays
   silence.

Stderr, not stdout: a diagnostic must never be able to corrupt program output, and
that is the guarantee ADR 0006 bought with the redirect.

## Consequences

- One line per unique deprecated symbol per file, naming the user's file, in
  Phel's own shape rather than PHP's.
- The dedup key is `realpath`-canonicalised, because the namespace scan and the
  compile name one file differently; the per-compile recording keeps its own
  dedup table, so a cache-replayed warm run still reports what the cold one did
  (#3222).
- A project whose error handler forwards PHP notices to a logger keeps receiving
  them, unchanged.
- The message text is now Phel's to shape. It has to stay one line: `warnSyntax()`
  and the detectors build a single string, and the cache stores it as one.

## Enforcement

- `ErrorNoticeTest`: the prefixed single line, the handler hand-off, and both
  silencing paths.
- `DeprecationNoticeOutputTest`: drives `bin/phel run` and pins the whole stderr
  of the issue's repro, including the absence of `ErrorNotice.php` and `string:1`.
- `DeprecationWarningsTest`: the notice reaches stderr but never a captured stdout
  buffer.

## Alternatives considered

- **Keep `trigger_error()` and silence one of PHP's two channels.** The
  `in <file> on line <n>` suffix is in PHP's formatter; no ini setting removes it.
- **Always write directly, never delegate.** Loses every notice for a project
  whose handler collects them, and floods PHPUnit runs that capture deprecations.
- **Drop the `string:<n>` report only.** Fixes the phantom location, leaves the
  doubling and the internal file.

## See also

[ADR 0006](0006-one-opt-in-deprecation-channel.md) ·
[ADR 0014](0014-announce-the-separator-deprecation.md) ·
`src/php/Compiler/CLAUDE.md`
