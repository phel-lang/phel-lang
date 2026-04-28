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

10. **Final refactor pass (mandatory before PR)**:
    Re-review every file touched by this change. Look for:
    - duplication introduced by the new code (extract or reuse)
    - dead branches, unused params, leftover debug
    - naming drift vs. surrounding module conventions
    - violations of `.claude/rules/php.md`, `modules.md`, `compiler.md`
    - over-engineering: speculative abstractions, premature interfaces

    Apply fixes. Re-run `composer test`. Commit as a separate refactor commit:
    ```bash
    git commit -m "ref(<scope>): polish <area> after #<issue-number>

    Related to #<issue-number>"
    ```
    Skip this commit only if the review surfaces zero changes.

11. **Create PR** using `/pr #<issue-number>`

## Checklist
- [ ] Issue fetched and understood
- [ ] Self-assigned
- [ ] Branch created from main
- [ ] Plan created and approved
- [ ] Tests written first (TDD)
- [ ] Implementation complete
- [ ] `composer test` passes
- [ ] Changelog updated
- [ ] Commit with issue reference
- [ ] Final refactor pass over all touched files (separate commit)
- [ ] PR created via `/pr`
