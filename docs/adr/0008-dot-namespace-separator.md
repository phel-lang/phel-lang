# ADR 0008: The namespace separator is `.`, and `\` still parses

- **Status**: Accepted (recorded retroactively; tracked in #1567)
- **Date**: 2026-07-29

## Context

Phel originally spelled namespaces the PHP way: `(ns app\core)`,
`(:require phel\string)`, `(phel\core/map inc xs)`. It matched the host and the
class FQNs next to it.

It also collides with the reader. `\` starts a character literal (`\a`,
`\newline`, `\uNNNN`) and escapes inside strings, which is why a class name as
data needs `"\\Phel\\Lang\\Keyword"`. Every Lisp reads `.` as the separator, and
the mismatch hits the audience the project targets hardest.

The dot form has worked for a long time. What kept the backslash alive: it is in
every existing project, every tutorial, and until #2827 every scaffold `phel init`
generated.

## Decision

`.` is the separator. `\` still parses everywhere it used to, is deprecated, and
warns through the opt-in channel
([ADR 0006](0006-one-opt-in-deprecation-channel.md)).

- Namespace declarations, `:require` targets (flat and `[ns :as alias]`),
  fully-qualified call sites, `:use` targets and leading-backslash class FQNs
  accept both; the backslash form warns.
- Class FQNs get the dotted reading: `Phel.Lang.ExceptionInfo`.
- Registry keys are dot-form (`"phel.core"`). `Munge::encodePhpNs` produces the
  backslash form for PHP `namespace` declarations and class FQNs;
  `Munge::encodeRegistryKey` the dot form for lookups. Two encoders, two genuinely
  different boundaries.
- No removal date. Removal happens no earlier than one full minor after the
  warning would be on by default.

## Consequences

The migration is safe (both forms compile to the same thing, a file may mix them)
and therefore slow: nothing breaks, so nothing forces the edit.

Detection is incomplete. `:refer` targets, `load` forms (strings) and reader-macro
forms carrying namespace strings as data are not warned about. Those positions
already accept the dot form and can be migrated by hand.

Keeping `\` keeps the lexer ambiguity alive: separator, character literal and
string escape share a lead byte. That cost falls on tooling, and it is the
strongest argument for eventual removal.

Everything shipped to users is on the dot form now. Scaffolds, the three bundled
example apps, the `.agents/` task recipes and the `--template=` scaffolds behind
them were all still on `\` until #2827, so generated projects started on syntax the
compiler warns about and agents learned the deprecated spelling first.

## Enforcement

- `Domain/Analyzer/Environment/BackslashSeparatorDeprecator`, through
  `DeprecationWarnings`
- `NamespaceNormalizerTest`, `ProjectTemplateGeneratorTest`: scaffolds emit dots
- `InitCommandTest`: end to end
- `tools/validate-agents.sh`, `composer test-agents`: shipped recipes and examples

## Alternatives considered

- **Break in a major, `.` only.** The backslash form is in every existing project,
  and language stability promises `1.0.0` source compiles on every later `1.x`.
- **Keep `\` primary.** Costs the Clojure audience and keeps the ambiguity
  permanently.
- **Dotted class FQNs only, `\` for namespaces.** Two rules instead of one, no
  gain.

## See also

- [Migration: backslash to dot](../migration/backslash-to-dot.md)
- [Language surface spec](../spec/language-surface.md), section 4
- [#1567](https://github.com/phel-lang/phel-lang/issues/1567)
