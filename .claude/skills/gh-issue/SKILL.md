---
description: Fetch a GitHub issue, create a branch, implement with TDD, and open a PR
argument-hint: "[issue-number]"
disable-model-invocation: true
---

# GitHub Issue Workflow

## Context

!`gh issue view ${ARGUMENTS#\#} --json title,body,labels,assignees,state 2>/dev/null || echo "Provide an issue number"`

## Instructions

### Phase 1: Setup

1. **Parse the issue number** from `$ARGUMENTS` (strip `#` if present)

2. **Assign yourself if unassigned**:
   ```bash
   gh issue edit <number> --add-assignee @me
   ```

3. **Create a branch** from `main` based on the issue type:

   Determine the branch prefix from labels:
   - `bug` → `fix/`
   - `enhancement` → `feat/`
   - `documentation` → `docs/`
   - No label → `feat/` (default)

   Branch name format: `<prefix><issue-number>-<slug>`

   ```bash
   git checkout main && git pull
   git checkout -b <branch-name>
   ```

### Phase 2: Plan

4. **Enter Plan Mode** to design the implementation:
   - Explore the codebase to understand affected areas
   - Identify files that need changes
   - Consider the module architecture (Gacela facades, module boundaries)
   - Plan the TDD approach (what tests to write first)

5. **Create implementation plan** with:
   - Summary of what the issue requires
   - List of files to create/modify
   - Test strategy (unit, integration)
   - Step-by-step implementation order

### Phase 3: Implement

6. **After plan approval**, implement following TDD:
   - Write failing tests first
   - Implement minimum code to pass
   - Refactor while keeping tests green

7. **Run full test suite**:
   ```bash
   composer test
   ```
   Fix ALL errors before proceeding.

### Phase 4: Ship

8. **Update CHANGELOG.md** — add entry under `## Unreleased`

9. **Commit changes**:
   ```bash
   git add <specific-files>
   git commit -m "<type>(<scope>): <description>

   Related to #<issue-number>"
   ```

10. **Final refactor commit (mandatory, last commit before PR)**:
    Re-review every file touched by this change. Look for:
    - duplication introduced by the new code (extract or reuse)
    - dead branches, unused params, leftover debug
    - naming drift vs. surrounding module conventions
    - violations of `.claude/rules/php.md`, `modules.md`, `compiler.md`
    - over-engineering: speculative abstractions, premature interfaces

    Apply fixes. Re-run `composer test`. Commit as a separate `ref(...)` commit — must be the final commit on the branch before PR:
    ```bash
    git commit -m "ref(<scope>): polish <area> after #<issue-number>

    Related to #<issue-number>"
    ```
    If review surfaces zero changes, record that fact in the PR body instead of skipping silently.

11. **Create PR** using `/pr #<issue-number>`

### Phase 5: Verify & Merge

12. **Wait for CI green** on the PR:
    ```bash
    gh pr checks <pr-number-or-branch> --watch
    ```
    Fix red checks on the branch (push fixes; re-watch). Do not proceed while any required check is failing.

13. **Merge with mandatory approval bypass when possible**:
    Once every required check is green, merge via admin bypass to satisfy the mandatory-approval rule:
    ```bash
    gh pr merge <pr-number> --squash --admin --delete-branch
    ```
    If `--admin` is rejected (token lacks admin, branch protection blocks bypass), fall back to `--auto --squash --delete-branch` and surface that the PR is awaiting human approval. Never `--no-verify` past a failing required check.

14. **Sync local main** after merge:
    ```bash
    git checkout main && git fetch origin main && git reset --hard origin/main
    ```

## Checklist
- [ ] Issue fetched and understood
- [ ] Self-assigned
- [ ] Branch created from fresh `origin/main`
- [ ] Plan created and approved
- [ ] Tests written first (TDD)
- [ ] Implementation complete
- [ ] `composer test` passes
- [ ] Changelog updated
- [ ] Feature commit with issue reference
- [ ] Final refactor commit (last commit on branch, separate `ref(...)`)
- [ ] PR created via `/pr`
- [ ] CI green (`gh pr checks --watch`)
- [ ] PR merged via `--admin --squash` (or `--auto` fallback if admin blocked)
- [ ] Local `main` synced to `origin/main`
