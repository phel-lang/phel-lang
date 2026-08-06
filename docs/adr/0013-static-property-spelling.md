# ADR 0013: A static property is `$prop` to read and a plain name to assign

- **Status**: Accepted
- **Date**: 2026-08-01
- **Amends**: [0007](0007-clojure-style-interop-is-the-source-spelling.md) — closes
  the assignable-static-property question that record left open.

## Context

PHP files class constants and static properties in separate namespaces, so one
class can carry `const slot` and `public static $slot` at once, and only the `$`
sigil tells `Foo::slot` from `Foo::$slot` apart. Every other host member Phel
reaches is unambiguous.

Until [#2907](https://github.com/phel-lang/phel-lang/issues/2907) a static
property had no working spelling at all. `(php/oset (php/:: \Foo slot) v)`, the
shape `NativeSymbolCatalog` advertised, emitted `\Foo::slot = v`, which PHP
rejects at parse time as an assignment to a class constant, and
`(set! \Foo/slot v)` reported "Cannot resolve to a var" because `set!` sent every
symbol down the var branch.

[ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md) closes with
"Still open: an assignable static property. Neither spelling works". That line is
a snapshot of the day it was written; accepted records are immutable
(`README.md`), so it stays, and this record is where the question is closed. ADR
0007's decision, that the Clojure-style spelling is the source spelling, is
unchanged and still in force.

## Decision

- **Read** carries the sigil: `\Foo/$prop`, expanded by `QualifiedMemberExpander`
  to `(php/:: \Foo $prop)`. A bare `\Foo/prop` keeps meaning the class constant it
  has always meant.
- **Assign** carries no sigil: `(set! \Foo/prop v)`, and the primitive
  `(php/oset (php/:: \Foo prop) v)` under it, both emit `\Foo::$prop = v`. A class
  constant is not assignable, so an assignment can only mean the property and
  there is no ambiguity for a sigil to resolve. A name that already carries one is
  passed through rather than doubled.
- **`set!`** reads a symbol place as a static property when the namespace names a
  class and the name could be a PHP member. Both halves are `QualifiedMemberSyntax`,
  the analyzer's rule for call and value position; anything else is a dynamic var,
  which is what keeps `(set! Foo/*x* v)` a var.
- **The sigil is refused** wherever a static property cannot live: an instance
  member, a chained hop, a method name
  ([#2915](https://github.com/phel-lang/phel-lang/issues/2915)). PHP would read
  the `$x` as one of its own variables, which no Phel binding defines.

## Consequences

- Read and write are spelled differently for one member, which reads like an
  oversight and is not: the sigil answers a question only the read position asks.
- A class carrying `const slot` and `static $slot` stays fully reachable, and
  neither spelling can silently reach the other member.
- No reflection is involved, so the meaning of a form does not depend on whether
  the class happened to be loaded, unlike the constant-versus-static-method rule
  in ADR 0007.
- Tooling has to learn the sigil: completion and hover still do not offer static
  properties ([#2916](https://github.com/phel-lang/phel-lang/issues/2916)).
- Instance property access is untouched: `(.-prop o)` and `(set! (.-prop o) v)`
  never take a sigil.

## Enforcement

- `tests/php/Integration/Fixtures/PhpObjectSet/set-static-property*.test` pin the
  emitted PHP for statement, return and expression context, the explicit-sigil
  spelling and a class name as the place.
- `QualifiedMemberValueRuntimeTest` covers read, write and the refused sigil.
- `QualifiedMemberSpellingParityTest` pins `QualifiedMemberSyntax` and the Phel
  restatement inside `set!` to one table.
- `tests/phel/core/set-bang.phel` covers the source spellings end to end.

## Alternatives considered

- **Require the sigil on both sides.** Symmetrical, but assignment has no
  ambiguity for it to resolve, and `(set! \Foo/$prop v)` next to
  `(set! (.-prop o) v)` invents a difference the host does not have. It still
  works, since a name that already carries the sigil is accepted.
- **Reflect on the class to tell a constant from a static property.** Would let
  the bare name mean either. Rejected: the class may not be loaded at analysis
  time, and a class carrying both would have its meaning decided by load order.
- **Leave the position unspelled.** The status quo #2907 documents: a signature
  advertised in the catalogue that has never compiled.

## See also

[Language surface](../spec/language-surface.md) ·
[Clojure divergences](../spec/clojure-divergences.md) ·
[ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md) ·
#2907, #2914, #2915
