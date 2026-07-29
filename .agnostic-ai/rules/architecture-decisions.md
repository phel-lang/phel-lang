---
description: Where the rationale behind the repository's shape lives, and when to add a record
---

# Architecture Decisions

`docs/adr/` holds one record per decision that shaped the repository: the context,
the decision, the consequences, and what fails when it is broken. Index and process
in `docs/adr/README.md`.

Read the relevant record **before** proposing to straighten something out that looks
wrong. These each look like an oversight from the outside and each was argued once:

- four accepted module cycles (ADR 0004), not zero
- `php/new` / `php/->` / `php/::` are deprecated as source yet remain the
  compilation target the Clojure-style shorthand expands into (ADR 0007)
- `\` still parses as a namespace separator alongside `.` (ADR 0008)
- static analysis stops at `src/` and never covers `tests/` (ADR 0010)
- deprecation warnings are off unless `--warn-deprecations` is passed (ADR 0006)

Add a record when a choice satisfies all three: somebody will ask "why is it like
this?" in a year, reversing it costs more than a pull request, and a reasonable
contributor would otherwise "fix" it. Ship it in the pull request that makes the
decision. Copy `docs/adr/template.md`, take the next number, add the index row.

An accepted ADR is immutable: supersede it with a new record instead of editing it.
Naming conventions, style and workflow are **not** decisions; they stay in these
rules.
