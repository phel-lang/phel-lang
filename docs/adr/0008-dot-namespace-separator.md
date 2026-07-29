# ADR 0008: The namespace separator is `.`, and `\` still parses

- **Status**: Accepted (recorded retroactively; tracked in #1567)
- **Date**: 2026-07-29

## Context

Phel originally spelled namespaces the PHP way, with a backslash: `(ns app\core)`,
`(:require phel\string)`, `(phel\core/map inc xs)`. It matched the host and it
matched the class FQNs sitting next to it in the same file.

It also collides with the reader. `\` starts a character literal (`\a`, `\newline`,
`\uNNNN`), so the separator and a literal share a lead character, and inside a
string it is the escape character, which is why a class name written as data needs
`"\\Phel\\Lang\\Keyword"`. Every Lisp reader in the world reads `.` as the
namespace separator, and every Clojure reader expects `app.core`. The mismatch is
felt hardest by exactly the audience the project targets.

The dot form has worked at the language level for a long time. What kept the
backslash alive is that it is in every existing project, every tutorial, and until
recently every scaffold `phel init` generated (fixed in
[#2827](https://github.com/phel-lang/phel-lang/issues/2827)).

## Decision

`.` is the namespace separator. `\` still parses everywhere it used to, is
deprecated, and warns under the opt-in channel from
[ADR 0006](0006-one-opt-in-deprecation-channel.md).

- Namespace declarations, `:require` targets (flat and `[ns :as alias]`),
  fully-qualified call sites, `:use` targets and leading-backslash class FQNs all
  accept both, and the backslash form warns.
- Class FQNs get the dotted reading too: `Phel.Lang.ExceptionInfo` alongside
  `\Phel\Lang\ExceptionInfo`.
- Registry keys are dot-form (`"phel.core"`); `Munge::encodePhpNs` produces the
  backslash form for PHP `namespace` declarations and class FQNs, and
  `Munge::encodeRegistryKey` the dot form for lookups. The two encoders exist
  because the two boundaries genuinely differ.
- No removal date. It is the one reader-level item whose fate is not settled, and
  removal happens no earlier than one full minor after the warning would be on by
  default.

## Consequences

The migration is unusually safe, because both forms compile to the same thing and
a file may mix them. That is also why it drags: nothing breaks, so nothing forces
the edit.

Detection is not yet complete. `:refer` targets, `load` forms (which take strings)
and reader-macro forms carrying namespace strings as data are not warned about,
and are listed as such rather than being quietly absent. Those positions already
accept the dot form, so they can be migrated by hand today.

Keeping `\` also keeps a lexer ambiguity alive: the separator, the character
literal and the string escape all lead with the same byte. That is a cost paid by
tooling rather than by users, and it is the strongest argument for eventually
removing the form rather than settling into two spellings forever.

Everything shipped to users is written in the dot form. Scaffolds, the three
bundled example apps, the task recipes installed as `.agents/`, and the
`--template=` scaffolds behind them were all still on `\` until #2827, which meant
a generated project started out on syntax the compiler warns about, and an agent
reading the recipes learned the deprecated spelling first.

## Enforcement

- `Domain/Analyzer/Environment/BackslashSeparatorDeprecator` detects, through
  `DeprecationWarnings`
- `tests/php/Unit/Run/Domain/Init/NamespaceNormalizerTest.php` and
  `ProjectTemplateGeneratorTest.php`: scaffolds emit the dot form
- `tests/php/Integration/Run/Command/Init/InitCommandTest.php`: end to end
- `tools/validate-agents.sh` and `composer test-agents` keep the shipped recipes
  and example apps honest

## Alternatives considered

- **Break in a major, `.` only.** Rejected for now: the backslash form is in every
  existing project, and the language stability promise says source that compiles
  on `1.0.0` compiles on every later `1.x`.
- **Keep `\` as the primary spelling.** Rejected: it costs the Clojure-reader
  audience and keeps the reader ambiguity permanently.
- **Support only dotted class FQNs and keep `\` for namespaces.** Rejected: two
  rules to remember instead of one, for no gain.

## See also

- [Migration: backslash to dot](../migration/backslash-to-dot.md): what is and is
  not detected today
- [Language surface spec](../spec/language-surface.md), section 4
- [#1567](https://github.com/phel-lang/phel-lang/issues/1567)
