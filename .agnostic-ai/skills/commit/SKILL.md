---
description: Fix, gate and commit the current change.
argument-hint: "[optional commit message]"
disable-model-invocation: true
allowed-tools:
  - "read"
  - "edit"
  - "shell(composer *)"
  - "shell(./vendor/bin/*)"
  - "shell(./bin/phel *)"
  - "shell(git *)"
  - "shell(agnostic-ai *)"
---

# Commit

## Context

Run first: `git diff --stat`, `git diff --cached --stat`, `git status --short`.

## Instructions

1. **Review** the diff and run `composer fix` for PHP changes that need style fixes. Review what it changed. For agent specs, use the gate in `.agnostic-ai/rules/build-test-and-development-commands.md`.

2. **Changelog**: add the entry for a user-facing change, per `.agnostic-ai/rules/workflow.md`, before staging.

3. **Stage** the intended changed files by name, including any changelog entry, never `git add -A`. Review `git diff --cached` to confirm the commit is complete.

4. **Message**: use the argument when given; otherwise write one from the staged diff, per `.agnostic-ai/rules/workflow.md`. Add `(<scope>)` when the change stays in one module. No emojis.

5. **Gate**: follow `.agnostic-ai/rules/build-test-and-development-commands.md`. Check the active pre-commit hook through `git rev-parse --git-path hooks/pre-commit`, which also works in worktrees. When it supplies the final gate, commit with `COMPOSER_PROCESS_TIMEOUT=0` so long runs complete. Otherwise run the applicable gate yourself. Fix any failure; never commit past it.

6. **Commit** with `git commit -m "<message>"`, then report the hash, message and files.
