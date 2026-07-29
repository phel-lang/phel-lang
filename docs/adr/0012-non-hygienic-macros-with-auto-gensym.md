# ADR 0012: Non-hygienic macros with auto-gensym

- **Status**: Accepted (recorded retroactively; the decision predates this record)
- **Date**: 2026-07-29

## Context

A macro system either guarantees hygiene (a macro's bindings can never capture a
caller's, by construction, as in Scheme's `syntax-rules`) or provides the tools to
achieve it and trusts the author to use them, as Clojure does.

Full hygiene costs a separate pattern language and a renaming layer between the
reader and the analyzer. Clojure's answer is cheaper and, for a Lisp whose macros
are ordinary functions over data, more predictable: qualify symbols at read time,
give binding names a one-character way to be fresh, and leave the rest to the
author. Phel's premise is Clojure semantics where PHP allows them, so the same
answer applies unless PHP makes it wrong. It does not.

## Decision

Macros are functions from forms to forms, evaluated at compile time, and hygiene
is opt-in with two mechanisms.

- **Quasiquote namespace-qualifies symbols** at read time, to the *defining*
  namespace. `` `(map f xs) `` resolves to `phel.core/map` regardless of what the
  caller has shadowed. This is the easy half, and it is automatic.
- **Auto-gensym (`x#`)** covers binding names. Inside a quasiquote, a trailing `#`
  produces a fresh symbol, consistent within that quasiquote:
  `` `(let [x# 1] (+ x# x#)) `` expands to `(let [x__1 1] (+ x__1 x__1))`.
  `GensymContext` holds the per-quasiquote map; `(gensym)` is the explicit form.
- **The rest is the author's job**, and the failure it permits is real: a `let` or
  `binding` name in a macro body silently shadows a homonymous global when
  unquoted into the expansion. No error, just a wrong expansion.

The convention that contains it is a role suffix on macro-local bindings
(`-arg`, `-flag`, `-val`, `-sym`, `-form`). The canonical bug, fixed in
`c1aec277`, was a local named `memoize-lru` shadowing the global function of the
same name inside `defn-builder`, which broke only the recursive case.

Qualification is not a defence here: a local binding shadows a qualified global
too, so `(let [inc …] (phel.core/inc 1))` calls the local.

## Consequences

Macro authorship is as expressive as Clojure's, and macros stay debuggable:
`(macroexpand-1 'form)` returns data a human reads, with no renaming layer to see
through.

The cost is a class of bug that is silent, rare and confusing, and it lands
hardest on the standard library, where macros reference globals by name
constantly. The mitigation is a checklist rather than a mechanism: list every
`let`-binding name in a macro body, check each against the globals in scope (own
namespace, `phel.core`, anything `use`d), suffix or gensym on collision, and add a
test exercising recursion or self-reference, since that is where a shadow diverges
from the global.

Two adjacent facts about the model matter as much as hygiene in practice. Only
forms inside a quasiquote reach runtime, so a computed value has to be spliced
with `~` and an identifier still has to resolve in the caller's namespace. And a
`defmacro` is only visible to forms *after* it, because top-level forms compile
and evaluate one at a time (see [ADR 0002](0002-compile-to-php-source.md)), so a
macro and its first use cannot share a compilation unit.

## Enforcement

None automated. A shadowing macro-local is a legal program, and rejecting it would
mean rejecting the model. `.claude/rules/macro-hygiene.md` carries the checklist,
and it is loaded before any edit to a `defmacro` body or a quasiquote. The `phel
lint` rules `phel/shadowed-binding` and `phel/unused-binding` catch some
neighbouring cases but not this one.

## Alternatives considered

- **Full hygiene (`syntax-rules`-style).** Rejected: a second pattern language,
  expansions that no longer read as the data the author wrote, and a divergence
  from Clojure for no PHP-side reason.
- **Automatic renaming of every macro-introduced binding.** Rejected: it breaks
  macros that deliberately capture (`anaphoric` patterns), and Phel has no
  `syntax-parameterize` equivalent to give the escape hatch back.
- **A lint rule for shadowed globals in macro bodies.** Not rejected on merit,
  just not built. A rule that flags a `let` name colliding with an in-scope global
  inside a `defmacro` would catch the canonical bug, at some false-positive cost.

## See also

- [Macros internals](../internals/macros.md)
- `.claude/rules/macro-hygiene.md`: the checklist and the worked bug
- [Language surface spec](../spec/language-surface.md): macro *expansions* are
  explicitly not frozen; only behaviour is
