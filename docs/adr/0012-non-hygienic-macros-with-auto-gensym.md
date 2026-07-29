# ADR 0012: Non-hygienic macros with auto-gensym

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

A macro system either guarantees hygiene by construction (`syntax-rules`) or
provides the tools and trusts the author (Clojure).

Full hygiene costs a separate pattern language and a renaming layer between reader
and analyzer. Clojure's answer is cheaper and more predictable for a Lisp whose
macros are ordinary functions over data. Phel follows Clojure unless PHP makes it
wrong, and it does not here.

## Decision

Macros are functions from forms to forms, run at compile time. Hygiene is opt-in
with two mechanisms.

- **Quasiquote namespace-qualifies symbols** at read time to the *defining*
  namespace, so `` `(map f xs) `` resolves to `phel.core/map` regardless of caller
  shadowing. Automatic.
- **Auto-gensym (`x#`)** covers binding names: a trailing `#` inside a quasiquote
  yields a fresh symbol, consistent within that quasiquote.
  `` `(let [x# 1] (+ x# x#)) `` expands to `(let [x__1 1] (+ x__1 x__1))`.
  `GensymContext` holds the per-quasiquote map; `(gensym)` is explicit.
- **The rest is the author's job.** A `let` or `binding` name in a macro body
  silently shadows a homonymous global when unquoted into the expansion. No error,
  wrong expansion.

Convention: suffix macro-local bindings by role (`-arg`, `-flag`, `-val`, `-sym`,
`-form`). The canonical bug, fixed in `c1aec277`, was a local `memoize-lru`
shadowing the global of the same name in `defn-builder`, breaking only the
recursive case.

Qualification is not a defence: a local shadows a qualified global too, so
`(let [inc …] (phel.core/inc 1))` calls the local.

## Consequences

- Authorship matches Clojure's, and expansions stay readable: `(macroexpand-1
  'form)` returns data with no renaming layer to see through.
- Cost is a silent, rare bug class, landing hardest on the stdlib where macros
  reference globals constantly. Mitigation is a checklist: list every `let`-binding
  name in a macro body, check each against in-scope globals (own namespace,
  `phel.core`, anything `use`d), suffix or gensym on collision, add a test
  exercising recursion or self-reference where a shadow diverges from the global.
- Only forms inside a quasiquote reach runtime, so computed values are spliced with
  `~` and identifiers must resolve in the caller's namespace.
- A `defmacro` is visible only to later forms, since top-level forms compile and
  evaluate one at a time ([ADR 0002](0002-compile-to-php-source.md)), so a macro and
  its first use cannot share a compilation unit.

## Enforcement

None. A shadowing macro-local is a legal program; rejecting it means rejecting the
model. `.agnostic-ai/rules/macro-hygiene.md` carries the checklist and is loaded
before editing a `defmacro` body or quasiquote. `phel/shadowed-binding` and
`phel/unused-binding` catch neighbouring cases, not this one.

## Alternatives considered

- **Full hygiene.** A second pattern language, expansions that no longer read as
  the data the author wrote, divergence from Clojure for no PHP-side reason.
- **Auto-renaming every macro-introduced binding.** Breaks deliberate capture, and
  Phel has no `syntax-parameterize` to give the escape hatch back.
- **A lint rule for shadowed globals in macro bodies.** Not rejected, just not
  built; it would catch the canonical bug at some false-positive cost.

## See also

[Macros](../internals/macros.md) · `.agnostic-ai/rules/macro-hygiene.md` ·
[Language surface spec](../spec/language-surface.md) (expansions are not frozen,
only behaviour)
