---
description: Where design rationale lives, and when to add a record
---

# Architecture Decisions

`docs/adr/` holds one record per decision: context, decision, consequences, and
what fails when it is broken. Index and process in `docs/adr/README.md`.

Read the record **before** proposing to straighten out something that looks wrong.
Each of these looks like an oversight and was argued once:

- four accepted module cycles (ADR 0004), not zero
- `php/new` / `php/->` / `php/::` are deprecated as source yet remain the
  compilation target the shorthand expands into (ADR 0007)
- `\` still parses as a namespace separator alongside `.` (ADR 0008)
- static analysis stops at `src/`, never `tests/` (ADR 0010)
- deprecation warnings are off unless `--warn-deprecations` (ADR 0006)

Add a record when all three hold: somebody will ask "why is it like this?" in a
year, reversing it costs more than a pull request, and a reasonable contributor
would otherwise "fix" it. Copy `docs/adr/template.md`, take the next number, add
the index row, ship it in the pull request that makes the decision.

Accepted ADRs are immutable: supersede, never edit. Conventions, style and workflow
are not decisions; they stay in these rules.
