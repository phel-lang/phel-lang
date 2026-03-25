---
description: Update CHANGELOG.md unreleased section from recent commits or manual entry
argument-hint: "[entry text]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(git *)"
---

# Update Changelog

## Context

!`git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`

## Instructions

1. Read `CHANGELOG.md` to understand current state.

2. If `$ARGUMENTS` is provided, add it as an entry under the appropriate category in `## Unreleased`.

3. If no arguments, analyze commits since last tag (see context) and draft entries:
   - `### Added` — new functionality (`feat:` commits)
   - `### Changed` — behavior changes (`ref:`, `perf:` commits)
   - `### Fixed` — bug fixes (`fix:` commits)
   - `### Removed` — removed features

4. Entry format:
   - Imperative mood: "Add" not "Added"
   - Code in backticks: `` `(fn arg)` ``
   - Under 100 chars per entry
   - Skip non-user-facing commits (`chore:`, CI, internal refactoring)
   - Prefix breaking changes with **BREAKING**

5. Edit `CHANGELOG.md` to add the entries. Present draft before writing if generating from commits.
