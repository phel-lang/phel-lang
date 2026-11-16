---
description: Deliberate choices that look like bugs, and where their records live.
---

# Architecture Decisions

Read the record in `docs/adr/` before changing what it governs. Its `README.md` holds the index and when and how to add, supersede or edit one.

Deliberate, not bugs:

- four accepted module cycles (ADR 0004)
- `php/new`, `php/->` and `php/::` are rejected as source but stay the target the shorthand compiles to (ADR 0007, 0018)
- `\` still parses as a namespace separator beside `.` (ADR 0008)
- a static property reads as `Foo/$prop` and assigns as `(set! Foo/prop v)` (ADR 0013)
- deprecation warnings stay off without `--warn-deprecations` (ADR 0006)
