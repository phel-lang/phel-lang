# ADR 0001: Record architecture decisions

- **Status**: Accepted
- **Date**: 2026-07-29

## Context

Rationale was spread across four places that each lose it at a different speed:
pull request threads, module `CLAUDE.md` files (the rule, not the argument),
`docs/stability.md` (what is promised, not why the promise stops there), and
architecture tests (a rule and no reason).

The symptom was repetition. Why four module cycles instead of zero, why `php/->`
is deprecated and still the compilation target, why PHPStan stops at `src/`. Each
answer took code archaeology to recover and ended in a review comment that was
lost again.

Documentation here is expected to fail a build: the spec is parsed by a test, the
public API is a snapshot. Rationale cannot be tested, which is why it needs a home.

## Decision

Decisions live in `docs/adr/`, one file each, numbered and never renumbered. An
accepted ADR is immutable and is superseded rather than edited.
`docs/adr/README.md` holds the index and the bar for earning a record.

0002 to 0012 are written retroactively for decisions already in force, each citing
the pull request or issue where the argument happened.

## Consequences

A second place to look, and the standing risk of any ADR set: a record describing
a decision the code no longer implements is worse than none, because it is quoted
with confidence.

Two things limit that. Records state decisions, not mechanics, so refactors do not
invalidate them. And each carries an **Enforcement** section naming the test or
rule that fails when the decision is broken, so a stale record points at something
that no longer exists.

An ADR is also how to say no to a recurring proposal without re-arguing it.

## Enforcement

None automated. Rationale cannot be unit tested, and a prose linter would check
format, not truth. The mechanical half lives in each record's Enforcement section.

## Alternatives considered

- **One `DECISIONS.md`.** Append-only files stop being read at a dozen entries,
  and there is nowhere to hang a status.
- **The wiki.** Does not travel with a checkout, cannot be reviewed in a pull
  request, cannot be pinned to the commit that made the decision.
- **Extend module `CLAUDE.md`.** Those are terse and agent-facing, and
  cross-cutting decisions belong to no module.

## See also

- [`template.md`](template.md), [Stability policy](../stability.md)
- `.agnostic-ai/rules/`: conventions, which are not decisions
