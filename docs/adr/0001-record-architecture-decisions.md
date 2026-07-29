# ADR 0001: Record architecture decisions

- **Status**: Accepted
- **Date**: 2026-07-29

## Context

Rationale lived in four places that each lose it: pull request threads, module
`CLAUDE.md` files (the rule, not the argument), `docs/stability.md` (the promise,
not why it stops there), architecture tests (a rule, no reason).

Symptom: repetition. Why four module cycles, why `php/->` is deprecated and still
the compilation target, why PHPStan stops at `src/`. Each answer took archaeology
to recover and ended in a review comment that was lost again.

Docs here are expected to fail a build. Rationale cannot be tested, so it needs a
home.

## Decision

Decisions live in `docs/adr/`, one numbered file each, never renumbered. Accepted
ADRs are immutable and superseded rather than edited. `docs/adr/README.md` holds
the index and the bar for earning a record.

0002 to 0012 are retroactive, each citing where the argument happened.

## Consequences

A second place to look, and the standing risk: a record describing a decision the
code no longer implements is worse than none, because it is quoted with confidence.

Two limits on that. Records state decisions, not mechanics, so refactors do not
invalidate them. Each has an **Enforcement** section naming what fails when the
decision breaks, so a stale record points at something gone.

Also how to decline a recurring proposal without re-arguing it.

## Enforcement

None. Rationale cannot be unit tested; a prose linter checks format, not truth. The
mechanical half lives in each record's Enforcement section.

## Alternatives considered

- **One `DECISIONS.md`.** Unread past a dozen entries, nowhere to hang a status.
- **The wiki.** Does not travel with a checkout, cannot be reviewed in a PR.
- **Extend module `CLAUDE.md`.** Terse and agent-facing; cross-cutting decisions
  belong to no module.

## See also

[`template.md`](template.md) · [Stability policy](../stability.md) ·
`.agnostic-ai/rules/` (conventions, not decisions)
