# ADR 0001: Record architecture decisions

- **Status**: Accepted
- **Date**: 2026-07-29

## Context

Phel's durable decisions were spread across four places that each lose them at a
different speed: pull request threads (unsearchable once merged), module
`CLAUDE.md` files (which state the rule but not the argument), `docs/stability.md`
(normative, so it says what is promised and not why the promise stops there), and
architecture tests (which fail with a rule and no rationale).

The visible symptom was repetition. The same questions came back: why does
`ModuleDependencyCycleTest` allow four cycles instead of zero, why is `php/->`
deprecated while remaining the thing the shorthand expands into, why does PHPStan
stop at `src/`. Each had a real answer that took a code archaeology session to
recover, and each recovery ended in a review comment that would be lost again.

The repository already has strong opinions about documentation that cannot fail:
the spec is parsed by a test, the public API is a snapshot, the changelog gates a
release. Rationale is the one category that cannot be tested, which is exactly why
it needs a home rather than being left to survive on its own.

## Decision

Architecture decisions live in `docs/adr/`, one file per decision, numbered
sequentially and never renumbered. An ADR is immutable once accepted: it is
superseded by a later record rather than edited. `docs/adr/README.md` is the index
and states when a decision earns a record.

The initial set (0002 to 0012) is written retroactively for decisions already in
force. Each cites the pull request or issue where the argument actually happened,
so the record is a summary with a source, not a reconstruction.

## Consequences

The obvious cost is a second place to look, and the failure mode of any ADR set is
drift: a record that describes a decision the code no longer implements is worse
than no record, because it is quoted with confidence. Two things hold that off.
Records state decisions rather than mechanics, so ordinary refactors do not
invalidate them, and each carries an **Enforcement** section naming the test or
rule that fails when the decision is broken. When that section names something
that no longer exists, the record is stale in a checkable way.

An ADR is also a place to say no. "This was decided, here is the argument, open an
issue if the argument is now wrong" is a cheaper answer to a recurring proposal
than re-litigating it, and a fairer one, because the reasoning is on the table.

## Enforcement

None automated, deliberately. Rationale cannot be unit tested, and a linter over
prose would enforce format rather than truth. The **Enforcement** section inside
each record is where the mechanical part lives, and reviewers are the check on the
rest.

## Alternatives considered

- **A single `DECISIONS.md`.** Rejected: append-only files stop being read at
  around a dozen entries, and there is nowhere to hang a status.
- **The project wiki.** Rejected: it does not travel with a checkout, cannot be
  reviewed in a pull request, and cannot be pinned to the commit that made the
  decision.
- **Extending module `CLAUDE.md` files.** Those are agent-facing, scannable, and
  deliberately terse. Rationale would bloat them, and cross-cutting decisions
  belong to no single module.
- **Nothing, keep answering in reviews.** This is what produced the problem.

## See also

- [`template.md`](template.md)
- [Stability policy](../stability.md)
- [`AGENTS.md`](../../AGENTS.md) and `.claude/rules/`: conventions, which are not
  decisions and stay there.
