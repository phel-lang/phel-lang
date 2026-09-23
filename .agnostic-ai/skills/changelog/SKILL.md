---
description: Update CHANGELOG.md unreleased section from recent commits or manual entry; follows the changelog rule
argument-hint: "[entry text | --optimize]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(git *)"
---

# Update Changelog

## Context

!`git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`

## Instructions

1. Read `CHANGELOG.md` to understand current state.

2. Mode select:
   - `$ARGUMENTS` empty → draft entries from commits since last tag.
   - `$ARGUMENTS == --optimize` → rewrite `## Unreleased` in place. No new entries.
   - Otherwise → treat `$ARGUMENTS` as entry text; place under correct category.

3. Follow `.agnostic-ai/rules/changelog.md` (section order, entry style, grouping) on every write. On `--optimize`, apply all of it to the whole `## Unreleased`: merge duplicate headings and sibling bullets, fold fixes to unreleased features into their bullet, trim internals.

4. Edit `CHANGELOG.md`. Present the draft before writing when generating from commits or running `--optimize`.
