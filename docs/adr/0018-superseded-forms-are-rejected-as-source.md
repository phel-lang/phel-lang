# ADR 0018: A superseded form is rejected as source and kept as the target

- **Status**: Accepted
- **Date**: 2026-09-10
- **Amends**: [ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md)

## Context

ADR 0007 made the Clojure-style spellings the only ones to write and deprecated
`php/new`, `php/->` and `php/::` as source. ADR 0006 and #2888 did the same for
`set-var`. The road-to-1.0 tracker (#2859) then listed all four under "1.0
removes", which cannot be done: ADR 0007 already rejected removal, because the
analyzer *emits* three of them. `(new \C 1)` becomes `(php/new \C 1)`, `(.m o)`
becomes `(php/-> o (m))`, and `binding` expands to `set-var`. Deleting them
leaves the compiler with nothing to emit.

The deprecation also could not stay as it was. A notice behind
`--warn-deprecations` for the whole of `1.x` means the migration never
completes, and the tracker's removal list stays untrue.

## Decision

The four forms are **rejected as source** from `1.0.0` and **kept as the
compiler's own target**.

Writing one is an `AnalyzerException` carrying `PHEL012`, with no flag to turn
it off. Nothing about the emitted code changes, and every capability stays
reachable: #2881, #2883 and #2887 closed the gaps in the Clojure-style
spellings before this became possible.

What separates the two roles is **where the head symbol was written**:

- No location: the analyzer synthesized it. `QualifiedMemberExpander` builds a
  `php/::` this way, and `BreakSymbol` and `FnPrePostConditionRewriter` build
  their heads unlocated for exactly this reason.
- A location whose expansion origin is the bundled stdlib: a stdlib macro wrote
  it. `binding` emits `set-var`, `set!` emits a `php/::`.
- Anything else: somebody wrote it, including a macro of the user's own, which
  is theirs to fix.

`php/` at large is untouched. It marks host access, which is what the rule in
ADR 0007 protects: `php/aget`, `php/aset`, `php/apush`, `php/aunset`, their
`-in` variants, `php/oset`, `php/ref`, `php/callable` and every
`(php/some_function …)` stay exactly as they were.

## Consequences

- The tracker's "1.0 removes" list is honest for these four once it is worded as
  rejection rather than removal. From the outside it reads as removal; the
  compiler's internals do not move.
- A method call on a local compiles to `is_string($x) ? $x::m() : $x->m()`,
  because the shorthand cannot prove the target is not a class name. `php/->`
  was the only way to assert it. 473 of the 474 stdlib call sites already went
  through the shorthand, so this is the status quo rather than a new cost, but
  eliding the check where the analyzer knows the target is an object is now
  worth doing on its own.
- The opt-in deprecation channel has no syntax deprecation left in it. What
  remains is the `\` separator, which announces by default under ADR 0014, and
  deprecated *definitions* such as `to-php-array`.
- Editor support for completing `php/new`, `php/->` and `php/::`
  (`PhpInteropCallScanner`, `PhpInteropContextResolver`) now completes a form
  the user is not allowed to write. Left in place deliberately: it costs
  nothing, and an editor meeting old source still explains it.

## Enforcement

- `SupersededFormRejectorTest` covers each role: written, synthesized, expanded
  from a stdlib macro, expanded from a user macro.
- `SupersededFormRejectionTest` compiles every Clojure-style shorthand and each
  rejected form, so the shorthand cannot start failing and the form cannot start
  being accepted.
- `LanguageSurfaceSpecTest` pins the spec's table against
  `SupersededFormRejector::supersededFormNames()`.

## Alternatives considered

- **Leave the deprecation as it is.** The migration never completes inside
  `1.x`, and the removal list stays untrue.
- **Remove the forms outright.** ADR 0007 rejected it and still does: they are
  the compilation target.
- **Reject in user code but exempt `vendor/`.** A dependency would keep a
  spelling the application cannot use, and the form would not really be gone.

## See also

[Language surface spec](../spec/language-surface.md#rejected-as-source-from-100) ·
[Deprecated surface](../migration/deprecated-surface.md) ·
[ADR 0006](0006-one-opt-in-deprecation-channel.md) ·
[ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md) ·
[#2859](https://github.com/phel-lang/phel-lang/issues/2859)
