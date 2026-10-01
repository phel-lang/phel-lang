---
description: Fix, gate and commit the current change.
argument-hint: "[optional commit message]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*), Bash(./bin/phel *), Bash(git *)"
x-codex:
  interface:
    display_name: Commit
---

# Commit

## Context

::target claude
!`git diff --stat`
!`git diff --cached --stat`
!`git status --short`
::end
::target codex
Run first: `git diff --stat`, `git diff --cached --stat`, `git status --short`.
::end

## Instructions

1. **Auto-fix**: run `composer fix` (rector, then cs-fixer). Review what it changed.

2. **Stage** the changed files by name, never `git add -A`.

3. **Message**: use the argument when given; otherwise write one from the staged diff, per `.agnostic-ai/rules/workflow.md`. Add `(<scope>)` when the change stays in one module. No emojis.

4. **Changelog**: a user-facing change needs its `CHANGELOG.md` entry, per `.agnostic-ai/rules/workflow.md`. Add it before committing.

5. **Gate**: the pre-commit hook runs `composer test-all` when PHP or Phel files are staged. If `.git/hooks/pre-commit` is missing, run `tools/git-hooks/init.sh`, or run `COMPOSER_PROCESS_TIMEOUT=0 composer test` yourself first. Fix any failure; never commit past it.

6. **Commit** with `git commit -m "<message>"`, then report the hash, message and files.
