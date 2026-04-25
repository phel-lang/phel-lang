---
description: Auto-fix, lint, test, and commit changes with a conventional commit message
argument-hint: "[optional commit message]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*), Bash(./bin/phel *), Bash(git *)"
---

# Commit

## Context

!`git diff --stat`
!`git diff --cached --stat`
!`git status --short`

## Instructions

### Phase 1: Auto-fix

1. Run rector + cs-fixer on changed files:
   ```bash
   composer fix
   ```

2. If fixer modified files, review the changes and stage them.

### Phase 2: Quality gates

Run each step in order. Stop and fix issues before continuing.

3. **Static analysis**:
   ```bash
   composer test-quality
   ```

4. **Unit + integration tests**:
   ```bash
   composer test-compiler
   ```

5. **Core tests** (only if `.phel` files changed):
   ```bash
   git diff --cached --name-only | grep -q '\.phel$' && composer test-core
   ```

If any step fails, fix the issue and re-run from that step. Do NOT proceed to commit with failures.

> Note: The pre-commit hook runs `composer test-all` on commit. These gates catch issues early to avoid a slow hook failure.

### Phase 3: Commit

6. **Stage files** — add specific changed files by name (never `git add -A`).

7. **Draft commit message** using conventional commit format:
   - If `$ARGUMENTS` is provided, use it as the commit message
   - Otherwise, analyze the staged diff and generate one
   - Prefixes: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
   - Add `(<scope>)` when changes are scoped to a single module
   - **NEVER mention AI tooling in the message**

8. **Commit**:
   ```bash
   git commit -m "<message>"
   ```

9. **CHANGELOG check** — if the commit prefix is `feat:` or `fix:`, verify that `CHANGELOG.md` has been updated under `## Unreleased`. If not, warn the user before committing.

10. Report: commit hash, message, and files included.
