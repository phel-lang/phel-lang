# GitHub Issue Workflow

Fetch a GitHub issue, create a branch, implement it end-to-end, and open a PR.

## Arguments
- `$ARGUMENTS` - Issue number (e.g., `2` or `#2`)

## Instructions

### Phase 1: Setup

1. **Parse the issue number** from `$ARGUMENTS` (strip `#` if present)

2. **Fetch issue details**:
   ```bash
   gh issue view <number> --json title,body,labels,assignees,state
   ```

3. **Assign yourself if unassigned**:
   ```bash
   gh issue edit <number> --add-assignee @me
   ```

4. **Create a branch** from `main` based on the issue type:

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

   **Example:** Issue #42 "Add user notifications" with `enhancement` label → `feat/42-add-user-notifications`

### Phase 2: Plan

5. **Analyze the issue**: Understand requirements from title and body

6. **Enter Plan Mode** to design the implementation:
   - Explore the codebase to understand affected areas
   - Identify files that need changes
   - Consider the module architecture (Gacela facades, module boundaries)
   - Plan the TDD approach (what tests to write first)

7. **Create implementation plan** with:
   - Summary of what the issue requires
   - List of files to create/modify
   - Test strategy (unit, integration)
   - Step-by-step implementation order

### Phase 3: Implement

8. **After plan approval**, implement following TDD:
   - Write failing tests first
   - Implement minimum code to pass
   - Refactor while keeping tests green

9. **Run full test suite**:
   ```bash
   composer test
   ```
   Fix ALL errors before proceeding.

### Phase 4: Ship

10. **Update CHANGELOG.md** — add entry under `## Unreleased`

11. **Commit changes**:
    ```bash
    git add <specific-files>
    git commit -m "<type>(<scope>): <description>

    Related to #<issue-number>"
    ```

    Commit guidelines:
    - Use conventional commit format
    - Reference the issue with "Related to #X"
    - **NEVER include AI references in commits**

12. **Create PR** using the `/pr` command:
    ```
    /pr #<issue-number>
    ```

## Example Usage

```
/gh-issue 2
/gh-issue #15
```

## Output Format

After fetching, present:

```
## Issue #<number>: <title>

**Labels:** <labels>
**State:** <state>
**Branch:** <branch-name>

### Description
<body content>

### Implementation Plan
1. ...
```

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
- [ ] PR created via `/pr`
