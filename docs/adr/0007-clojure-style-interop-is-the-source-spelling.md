# ADR 0007: Clojure-style interop is the source spelling

- **Status**: Accepted (recorded retroactively; decided across #2875 to #2887)
- **Date**: 2026-07-29

## Context

Phel reached PHP through forms that came first and read like PHP:
`(php/new \DateTime …)`, `(php/-> obj (format "Y"))`, `(php/:: \DateTime (…))`.
Clojure-style shorthands were added later as sugar over them.

Two spellings for one operation taxes everything: docs show one and search results
the other, agents pick whichever they saw last, a Clojure reader learns a second
dialect. The stdlib used the older spelling, so the most-read Phel code taught the
discouraged form.

Both survived because some positions had only the `php/*` spelling: a static method
as a value, an instance method as a function of its receiver, a name computed at
expansion time. #2881, #2883 and #2887 closed them.

## Decision

> **`php/` means host access. It is never a second spelling for something Phel
> already says the Clojure way.**

- `php/new`, `php/->`, `php/::` are **deprecated as source**; the Clojure-style form
  is the only spelling to write. They remain the compilation target and work for
  all of `1.x`.
- Shorthands are analyzer sugar expanded before analysis, deliberately not special
  forms: call position in `AnalyzePersistentList`, value position in
  `QualifiedMemberExpander`.
- The rest of `php/*` stays. `php/aget`, `php/aset`, `php/apush`, `php/aunset`,
  `php/oset` mutate in place, `php/ref` takes a reference, `php/callable` makes a
  callable. `phel.core` spells the common ones at top level (`aget`, `aset`,
  `aclone`, `alength`, `set!`).
- A macro computing a method or class name builds the head symbol,
  `` `(~(symbol (str "." name)) ~recv ~@args) ``.
- The stdlib and `docs/` are written in the shorthand (#2875, #2882, #2904).

## Consequences

- A form can be deprecated and load-bearing: `php/->` warns when written and is
  emitted on your behalf a moment later. `SupersededFormDeprecator` therefore runs
  in `AnalyzePersistentList::analyze()` **before** the shorthand expansions, or
  every shorthand would warn about itself. It also ignores an unlocated head,
  keeping `QualifiedMemberExpander`'s synthesized `php/::` quiet.
- Removal is a major-release change, not scheduled here.
- Ambiguity inherited from PHP: in value position a qualified member is a class
  constant unless the class has no such constant and does have a public static
  method, decided by reflection at analysis time. A class with both keeps the
  constant, so `\C/new` is never a constructor. At bare-host-symbol fallback a
  class, interface, trait or enum beats a same-named global constant; `php/NAME` is
  the escape hatch.
- Still open: an assignable static property. Neither spelling works, so `php/::` is
  not the answer either
  ([#2907](https://github.com/phel-lang/phel-lang/issues/2907)).

## Enforcement

- `LanguageSurfaceSpecTest`: checks the spec's deprecated table against
  `SupersededFormDeprecator`, and fails on a spec row with no dispatch entry, which
  is how shorthands stay out of the special-form list
- `SupersededFormDeprecationTest`, `QualifiedMemberValueRuntimeTest`
- `core-api.snapshot.txt` pins the stdlib surface across the rewrite

## Alternatives considered

- **Keep both, document a preference.** The status quo that produced a stdlib in
  the discouraged form.
- **Remove the three forms.** They are the compilation target; removal inside `1.x`
  breaks the spec's promise.
- **Deprecate all of `php/*`.** The mutating forms and `php/ref` reach capabilities
  with no Clojure counterpart.

## See also

[Language surface spec](../spec/language-surface.md#interop-shorthands) ·
[Deprecated surface](../migration/deprecated-surface.md) ·
`src/php/Compiler/CLAUDE.md` · [ADR 0006](0006-one-opt-in-deprecation-channel.md)
