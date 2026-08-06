# ADR 0008: The namespace separator is `.`, and `\` still parses

- **Status**: Accepted, amended by [0014](0014-announce-the-separator-deprecation.md)
  (recorded retroactively; tracked in #1567)
- **Date**: 2026-07-29
- **Amended by**: [0014](0014-announce-the-separator-deprecation.md) — the warning
  is no longer opt-in, and [0015](0015-a-php-class-is-named-with-dots.md) fixes the
  removal to the next major rather than to a minor boundary.

## Context

Phel spelled namespaces the PHP way: `(ns app\core)`, `(:require phel\string)`,
`(phel\core/map inc xs)`. It matched the host and the class FQNs next to it.

It also collides with the reader. `\` starts a character literal (`\a`, `\newline`,
`\uNNNN`) and escapes inside strings, which is why a class name as data needs
`"\\Phel\\Lang\\Keyword"`. Every Lisp reads `.` as the separator, so the mismatch
hits the target audience hardest.

The dot form has worked for a long time. What kept `\` alive: every existing
project, every tutorial, and until #2827 every scaffold `phel init` generated.

## Decision

`.` is the separator. `\` still parses everywhere it did, is deprecated, and warns
through the one deprecation channel ([ADR 0006](0006-one-opt-in-deprecation-channel.md)).
That channel was opt-in when this was recorded;
[0014](0014-announce-the-separator-deprecation.md) made this one detector announce
whether or not the flag is set.

- Namespace declarations, `:require` targets (flat and `[ns :as alias]`),
  fully-qualified call sites, `:use` targets and leading-backslash class FQNs accept
  both; the backslash form warns.
- Class FQNs get the dotted reading: `Phel.Lang.ExceptionInfo`.
- Registry keys are dot-form (`"phel.core"`). `Munge::encodePhpNs` emits the
  backslash form for PHP `namespace` declarations and class FQNs,
  `Munge::encodeRegistryKey` the dot form for lookups: two genuinely different
  boundaries.
- No removal date, and no earlier than one full minor after the warning would be on
  by default.

## Consequences

Both forms compile to the same thing and a file may mix them, so the migration is
safe and therefore slow: nothing breaks, so nothing forces the edit.

Detection is incomplete. `:refer` targets, `load` forms (strings) and reader-macro
forms carrying namespace strings as data are not warned about. They already accept
the dot form and can be migrated by hand.

Keeping `\` keeps the lexer ambiguity: separator, character literal and string
escape share a lead byte. That cost falls on tooling and is the strongest argument
for eventual removal.

Everything shipped is on the dot form now. Scaffolds, the three example apps, the
`.agents/` recipes and the `--template=` scaffolds were all still on `\` until
#2827, so generated projects started on syntax the compiler warns about and agents
learned the deprecated spelling first.

## Enforcement

- `Domain/Analyzer/Environment/BackslashSeparatorDeprecator`, through
  `DeprecationWarnings`
- `NamespaceNormalizerTest`, `ProjectTemplateGeneratorTest`, `InitCommandTest`
- `tools/validate-agents.sh`, `composer test-agents`

## Alternatives considered

- **Break in a major, `.` only.** `\` is in every existing project, and stability
  promises `1.0.0` source compiles on every later `1.x`.
- **Keep `\` primary.** Costs the Clojure audience, keeps the ambiguity forever.
- **Dotted class FQNs only.** Two rules instead of one, no gain.

## See also

[Migration: backslash to dot](../migration/backslash-to-dot.md) ·
[Language surface spec](../spec/language-surface.md) ·
[#1567](https://github.com/phel-lang/phel-lang/issues/1567)
