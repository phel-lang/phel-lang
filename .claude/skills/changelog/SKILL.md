---
description: Update CHANGELOG.md unreleased section from recent commits or manual entry; enforces simple, optimized notes
argument-hint: "[entry text | --optimize]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(git *)"
---

# Update Changelog

## Context

!`git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`

## Instructions

1. Read `CHANGELOG.md` to understand current state.

2. Mode select:
   - `$ARGUMENTS` empty → draft entries from commits since last tag.
   - `$ARGUMENTS == --optimize` → rewrite `## Unreleased` in place per style rules below. No new entries.
   - Otherwise → treat `$ARGUMENTS` as entry text; place under correct category.

3. Categories:
   - `### Added` — new functionality (`feat:`)
   - `### Performance` — perf wins (`perf:`)
   - `### Changed` — behavior changes (`ref:`, BC)
   - `### Fixed` — bug fixes (`fix:`)
   - `### Removed` — removed features

4. Style rules (apply on every write, enforce on `--optimize`):
   - Imperative mood: "Add" not "Added"
   - Code in backticks: `` `(fn arg)` ``
   - Cap ~120 chars per entry; split or move detail to PR body if longer
   - PR ref at end: `(#NNNN)`. Multiple PRs same bullet → `(#A #B #C)`
   - Drop filler: "now", "improved", "new", "simply", "basically", marketing voice ("blazing", "powerful")
   - Lead with what changed, not why. Why → PR body.
   - Skip non-user-facing commits (`chore:`, CI internals, test-only refactors)
   - Prefix BC with `BC:` or **BREAKING**
   - Drop redundant prefix when section implies it ("Compiler:" inside `### Performance` of compiler-only release). Keep when ambiguous.

5. Performance section clustering (always on):
   Group bullets under sub-labels matching the change locus. Example labels (use what fits):
   - `Dispatch / call sites:`
   - `Compile-time folding / hoisting:`
   - `Type-driven call specialisation:`
   - `Runtime data structures:`
   - `Emit size:`

6. Combine sibling entries:
   - N PRs touching same subsystem → 1 bullet, list PR refs at end.
   - Same verb + same target across categories → merge.

7. Edit `CHANGELOG.md`. Present draft before writing when generating from commits or running `--optimize`.
