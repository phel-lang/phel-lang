---
description: Draft or tidy the Unreleased changelog section.
argument-hint: "[entry text | --optimize]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(git *)"
x-codex:
  interface:
    display_name: Changelog
---

# Update Changelog

## Context

::target claude
!`git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`
::end
::target codex
Run first: `git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`.
::end

## Instructions

1. Read `CHANGELOG.md` to understand current state.

2. Mode select:
   - No argument → draft entries from commits since last tag.
   - The argument is `--optimize` → rewrite `## Unreleased` in place. No new entries.
   - Otherwise → treat the argument as entry text; place under correct category.

3. Follow `.agnostic-ai/rules/changelog.md` (section order, entry style, grouping) on every write. On `--optimize`, apply all of it to the whole `## Unreleased`: merge duplicate headings and sibling bullets, fold fixes to unreleased features into their bullet, trim internals.

4. Edit `CHANGELOG.md`. Present the draft before writing when generating from commits or running `--optimize`.
