# ADR 0007: Clojure-style interop is the source spelling

- **Status**: Accepted (recorded retroactively; decided across #2875 to #2887)
- **Date**: 2026-07-29

## Context

Phel reaches PHP through special forms that came first and read like PHP:
`(php/new \DateTime "2020-01-01")`, `(php/-> obj (format "Y"))`,
`(php/:: \DateTime (createFromFormat …))`. Clojure-style shorthands
(`(new \DateTime …)`, `(.format obj "Y")`, `(\DateTime/createFromFormat …)`) were
added later as sugar over them.

Two spellings for one operation is a tax on everything: documentation shows one
and search results show the other, an agent writing Phel picks whichever it saw
last, and a reader arriving from Clojure has to learn a second dialect for
something they already know how to say. The standard library itself was written
in the older spelling, so the most-read Phel code in existence taught the form the
project wanted people to stop writing.

The reason both survived was not taste. Some positions had **only** the `php/*`
spelling: a static method as a value, an instance method as a function of its
receiver, a method name computed at expansion time. As long as those gaps existed,
`php/->` was a necessary fallback and could not be deprecated honestly.

[#2881](https://github.com/phel-lang/phel-lang/issues/2881),
[#2883](https://github.com/phel-lang/phel-lang/issues/2883) and
[#2887](https://github.com/phel-lang/phel-lang/issues/2887) closed the last of
them.

## Decision

One rule decides which interop forms exist:

> **`php/` means host access. It is never a second spelling for something Phel
> already says the Clojure way.**

Applying it:

- `php/new`, `php/->` and `php/::` are **deprecated as source** and the
  Clojure-style form is the only spelling to write. They remain the compilation
  target the shorthand expands into, and they keep working for all of `1.x`.
- The shorthands are analyzer sugar, expanded before analysis, and are
  deliberately *not* special forms. Call position is handled in
  `AnalyzePersistentList`, value position in `QualifiedMemberExpander`.
- The rest of `php/*` stays, because each reaches a PHP capability Phel has no
  other word for: `php/aget`, `php/aset`, `php/apush`, `php/aunset` and `php/oset`
  mutate in place, `php/ref` takes a PHP reference, `php/callable` makes a PHP
  callable. `phel.core` spells the common ones at top level (`aget`, `aset`,
  `aclone`, `alength`, `set!`).
- A macro whose method or class name is computed at expansion time builds the head
  symbol, `` `(~(symbol (str "." name)) ~recv ~@args) ``, rather than falling back
  to `php/->`.
- The standard library and `docs/` are written in the shorthand (#2875, #2882,
  #2904), so the most-read Phel code teaches the intended form.

## Consequences

The deprecation is unusual and needs stating plainly: a form can be both
deprecated and load-bearing. `php/->` warns when you write it and is emitted by
the compiler on your behalf a moment later. `SupersededFormDeprecator` therefore
runs in `AnalyzePersistentList::analyze()` **before** the shorthand expansions,
because afterwards every shorthand would warn about itself. It also ignores an
unlocated head, which is how `QualifiedMemberExpander`'s synthesized `php/::`
stays quiet.

Removing the forms is a major-release change and is not scheduled here.

Some ambiguity is inherited from PHP rather than invented. In value position a
qualified member is a class constant unless the class has no constant of that name
and does have a public static method, decided by reflection at analysis time. A
class with both keeps the constant, which is why `\C/new` is never a constructor.
At bare-host-symbol fallback an existing class, interface, trait or enum beats the
global-constant reading of the same name, and `php/NAME` is the explicit escape
hatch.

One position is still open: an assignable static property. Neither spelling works
today, so `php/::` is not the answer for it either. Tracked in
[#2907](https://github.com/phel-lang/phel-lang/issues/2907).

## Enforcement

- `tests/php/Unit/Architecture/LanguageSurfaceSpecTest.php` checks the deprecated
  table in the language surface spec against `SupersededFormDeprecator`, and fails
  on a spec row with no dispatch entry (which is how the shorthands are kept out
  of the special-form list)
- `tests/php/Integration/Compiler/SupersededFormDeprecationTest.php`
- `tests/php/Integration/Compiler/QualifiedMemberValueRuntimeTest.php`
- `tests/php/Integration/Api/core-api.snapshot.txt` pins the stdlib surface across
  the rewrite

## Alternatives considered

- **Keep both spellings, document a preference.** This was the status quo, and it
  is what produced a standard library written in the form the docs discouraged.
- **Remove `php/new`, `php/->`, `php/::` outright.** Rejected: they are the
  compilation target, and removing them inside `1.x` would break the promise the
  spec makes.
- **Deprecate the whole `php/*` family.** Rejected: the mutating forms and
  `php/ref` reach capabilities with no Clojure counterpart. The rule is about
  duplicate spellings, not about the prefix.

## See also

- [Language surface spec: interop shorthands](../spec/language-surface.md#interop-shorthands)
- [The currently deprecated surface](../migration/deprecated-surface.md)
- `src/php/Compiler/CLAUDE.md`: expansion points and the reflection rule
- [ADR 0006](0006-one-opt-in-deprecation-channel.md)
