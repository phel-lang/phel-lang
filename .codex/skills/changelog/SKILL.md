---
name: changelog
description: Update CHANGELOG.md under Unreleased. Use when Codex is asked to add a changelog entry, derive changelog text from recent commits, or verify whether a user-facing change needs release notes.
---

# Changelog

## Workflow

1. Read `CHANGELOG.md` and preserve its existing structure.
2. If the user provides entry text, place it under the appropriate `## Unreleased` category.
3. If no entry text is provided, inspect commits since the latest tag:
   ```bash
   git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline
   ```
4. Draft entries from user-facing commits:
   - `feat:` -> `### Added`
   - `ref:` or `perf:` -> `### Changed`
   - `fix:` -> `### Fixed`
   - removals -> `### Removed`
5. Skip non-user-facing commits such as chores, CI-only changes, and internal refactors.
6. Use imperative mood, keep entries under 100 characters when practical, wrap code identifiers in backticks, and prefix breaking changes with `**BREAKING**`.
7. When generating entries from commits, present the draft before editing if the mapping is ambiguous.
