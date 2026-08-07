# ADR 0015: A PHP class is named with dots and no leading marker

- **Status**: Accepted. Reviewed by @jasalt, including the lower-case vendor
  namespace trade-off
  ([#2876](https://github.com/phel-lang/phel-lang/issues/2876#issuecomment-5159303444)).
- **Date**: 2026-08-02
- **Supersedes**: none. Settles the question [ADR 0008](0008-dot-namespace-separator.md) left open.

## Context

Phel spells a PHP class three ways today: `\Phel\Lang\Symbol`, `Phel.Lang.Symbol`,
and, for a root class, `\DateTime` or `DateTime`. The leading `\` is not the
separator, it is a marker meaning "this is a host class", and
[#2876](https://github.com/phel-lang/phel-lang/issues/2876) exists because the two
questions kept being conflated: dropping the separator does not by itself decide
the marker's fate.

Clojure needs no marker. `java.util.Date` is unambiguous because JVM packages are
lower case and class names are not, and because a `def` **cannot** shadow a class:

```clojure
user=> (def RuntimeException "shadow")
Syntax error compiling def at (REPL:1:1).
Expecting var, but RuntimeException is mapped to class java.lang.RuntimeException
```

PHP is close enough for the same shape to work. A vendor namespace is
conventionally PascalCase (`Symfony\Component\…`, `League\Uri\…`), so an
upper-case first segment identifies a host class as reliably as lower case
identifies a package on the JVM. Of the 99 PSR-4 namespaces installed in this
repository, one begins lower case. PHP class names are case-insensitive once a
class is loaded, but that does not make recasing a portable source spelling:
an autoloader may need the declared prefix casing to load the class first.

The push to write Phel's own sources in the dot spelling, and the observation
that Clojure's refusal is what makes a bare class name safe, both came from
@jasalt. This record is those two arguments carried to their conclusion rather
than a separate proposal.

Two things were missing, and both landed first:

- Phel's own sources modelled the old spelling, so no migration could be argued
  from them ([#2926](https://github.com/phel-lang/phel-lang/pull/2926)).
- A `def` silently shadowed a class, which is what made a bare name genuinely
  less safe than a marked one ([#2934](https://github.com/phel-lang/phel-lang/pull/2934),
  suggested by @jasalt). It now warns.

## Decision

**The destination is the Clojure shape.** A PHP class is written with the dot
separator and no marker:

```phel
(new DateTime "now")
(new Phel.Lang.Keyword "x")
Phel.Lang.Symbol
Symfony.Component.Console.Command.Command/SUCCESS
```

1. `.` is the separator, for Phel namespaces and PHP class names alike.
2. A class reference is identified lexically, by an upper-case first segment.
   No reflection, so meaning never depends on what happened to be loaded.
3. A namespace whose first segment is lower case (`phpDocumentor\Reflection\…`)
   is reached by importing it, `(:use phpDocumentor.Reflection.DocBlock)`, which
   is Phel's `:import`. An upper-case spelling may resolve after PHP has already
   loaded the class, because PHP class names are case-insensitive, but it can
   fail when Composer must autoload the lower-case PSR-4 prefix. It is therefore
   not part of the language contract. This is the one place PHP's conventions
   are looser than the JVM's, and an import is the answer Clojure already gives.
4. **The leading `\` retires with the separator**, at the next major. It is not
   promoted to a permanent marker, and `\Phel.Lang.Symbol` is deliberately never
   made to work: a marker that survives is a second way to say one thing.
5. The `def`-shadows-a-class **warning becomes a refusal** in that same major.
   That is what makes a bare name safe, and it is the guarantee Clojure gives.

**Not at 1.0.** `\` keeps working for all of `1.x`, as
[the upgrade guide](../migration/upgrade-0.49-to-1.0.md) already promises. Its
notice period only starts at `1.0.0`, when the deprecation begins announcing
without a flag ([ADR 0014](0014-announce-the-separator-deprecation.md)).
[#2827](https://github.com/phel-lang/phel-lang/issues/2827) carries out the
removal.

## Consequences

- One spelling to teach, and it is the one a Clojure reader already knows.
- `1.x` accepts both, warns about one, and breaks nothing.
- Removal at the major is now a mechanical change for users: the same sweep that
  moved this repository's own sources is a `\` to `.` rewrite over multi-segment
  names, and the deprecation names every site.
- A project that defines a name colliding with a PHP class gets a warning in
  `1.x` and an error at the major, which is a real behaviour change and the
  reason it is not being done sooner.
- `Munge::encodePhpNs()` keeps emitting `\` for PHP namespace declarations and
  class FQNs in generated code. Only the *source* spelling is settled here.
- Class names as **data** are unaffected: `"\\App\\Status"` is a PHP string, and
  `(.cases "\\App\\Status")` keeps its backslashes.

## Enforcement

- `QualifiedMemberSpellingParityTest` pins the lexical class-reference rule
  across the analyzer and `phel.core/set!`.
- `ClassShadowWarnerTest` pins the warning that becomes the refusal.
- `BackslashSeparatorDeprecatorTest` pins that every position still announces.
- The stdlib and the Phel test suite are written in the destination spelling, so
  a regression to `\` shows up as a diff rather than as an opinion.

## Alternatives considered

- **Keep `\` as a permanent host marker.** Two ways to name one thing, forever,
  and the reason it looked necessary (shadowing) is being removed instead.
- **Remove `\` at 1.0.** Breaks the published promise of the upgrade guide and
  lands on users whose notice period began the same week.
- **Make `\Phel.Lang.Symbol` compose.** Preserves the marker this record retires,
  and adds a fourth spelling on the way to having one.
- **Resolve a lower-case-initial namespace without an import.** Needs a rule
  beyond "upper case is a class", and every candidate makes a name's meaning
  depend on what is loaded.

## See also

[ADR 0008](0008-dot-namespace-separator.md) ·
[ADR 0014](0014-announce-the-separator-deprecation.md) ·
[backslash-to-dot.md](../migration/backslash-to-dot.md) · #2876, #2827, #2926, #2934
