# ADR 0018: A superseded form is rejected as source and kept as the target

- **Status**: Accepted
- **Date**: 2026-09-11
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

What separates the two roles is **the compiler phase that produced the form**.
The check runs in the reader, on each top-level form as it is read, and not in
the analyzer:

- The reader turns typed characters into forms, so everything it produces was
  typed by somebody.
- Macro output is built during analysis and never passes through the reader.
- A quasiquote is already lowered when the check runs, so a macro template reads
  as `(apply list (concat (list (quote php/new)) …))`. The name survives as
  quoted data, never as a list head, and a template keeps compiling. This is
  what lets `binding` emit `set-var` and `set!` emit a `php/::`.
- A plain `quote` is skipped outright, because `'(php/new \C)` is data.

A user's own macro that emits one of these keeps working for the same reason a
stdlib one does. The user writes the replacement in new source; a template that
still names the old form compiles until they get to it.

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

- `SupersededFormRejectorTest` covers the walk: each of the four heads, a head
  nested inside a larger form, a quoted subtree, and a `php/*` form that stays.
- `SupersededFormRejectionTest` compiles every Clojure-style shorthand and both
  compiles and evaluates each rejected form, so neither public execution path can
  accept one.
- `LanguageSurfaceSpecTest` pins the spec's table against
  `SupersededFormRejector::supersededFormNames()`.

## Alternatives considered

- **Leave the deprecation as it is.** The migration never completes inside
  `1.x`, and the removal list stays untrue.
- **Remove the forms outright.** ADR 0007 rejected it and still does: they are
  the compilation target.
- **Reject in user code but exempt `vendor/`.** A dependency would keep a
  spelling the application cannot use, and the form would not really be gone.
- **Decide in the analyzer, from the head symbol's location.** Tried first, and
  it fails in two directions. Treating an expansion origin under `src/phel` as
  compiler-generated breaks inside a PHAR, where the bundled stdlib lives under
  `phar://…/src/phel` and the path test returns false, so every `deftest` in a
  scaffolded project stops compiling. Treating an absent location as
  compiler-generated breaks under nested expansion: `deftest` expands to
  `binding`, which builds `set-var`, and the outer expansion stamps a location
  onto the freshly built head. Both signals are guesses about who wrote a form;
  the reader knows.

## See also

[Language surface spec](../spec/language-surface.md#rejected-as-source-from-0520) ·
[Deprecated surface](../migration/deprecated-surface.md) ·
[ADR 0006](0006-one-opt-in-deprecation-channel.md) ·
[ADR 0007](0007-clojure-style-interop-is-the-source-spelling.md) ·
[#2859](https://github.com/phel-lang/phel-lang/issues/2859)
