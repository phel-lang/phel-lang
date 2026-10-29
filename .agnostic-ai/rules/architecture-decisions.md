---
description: Where design rationale lives, and when to add a record
---

# Architecture Decisions

Read the accepted record before changing the behavior or structure it governs. Index and process: `docs/adr/README.md`.

Deliberate choices that can look like bugs:

- four accepted module cycles (ADR 0004)
- `php/new` / `php/->` / `php/::` are rejected as source yet remain the
  compilation target the shorthand expands into (ADR 0007, amended by ADR 0018)
- `\` still parses as a namespace separator alongside `.` (ADR 0008)
- a static property reads as `Foo/$prop` and assigns as `(set! Foo/prop v)`,
  sigil on one side only (ADR 0013)
- static analysis stops at `src/`, never `tests/` (ADR 0010)
- deprecation warnings are off unless `--warn-deprecations` (ADR 0006)

Add a record when its rationale will matter in a year, reversing it costs more than one PR, and a reasonable contributor would otherwise "fix" it. Use `docs/adr/template.md`, the next number and an index row; ship it with the change.

Accepted ADRs are immutable: supersede, never edit. Conventions, style and workflow belong in rules.
