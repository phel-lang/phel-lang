---
name: commit
description: Prepare and create a conventional commit for Phel changes. Use when Codex is asked to auto-fix, lint, test, stage specific files, draft a commit message, or commit current repository changes.
---

# Commit

## Workflow

1. Inspect current changes:
   ```bash
   git diff --stat
   git diff --cached --stat
   git status --short
   ```

2. Run auto-fixers when appropriate:
   ```bash
   composer fix
   ```
   Review any fixer changes before staging them.

3. Run quality gates in order, fixing failures before continuing:
   ```bash
   composer test-quality
   composer test-compiler
   ```

4. If staged or changed `.phel` files exist, run:
   ```bash
   composer test-core
   ```

5. Stage files by explicit path. Avoid `git add -A` unless every changed file is understood and intended.

6. Draft a conventional commit message:
   - use the user's message when provided
   - otherwise derive it from the staged diff
   - valid prefixes include `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`, `perf:`
   - add a scope when changes clearly belong to one module
   - do not mention AI tooling in the commit message

7. For `feat:` or `fix:` commits, verify `CHANGELOG.md` has an `## Unreleased` entry when the change is user-facing.

8. Commit and report the hash, message, and included files:
   ```bash
   git commit -m "<message>"
   ```
