---
description: Push branch and create a PR with concise description and labels
argument-hint: "[issue-number]"
x-claude:
  allowed-tools: "Read, Edit, Bash(git *), Bash(gh *)"
---

# Create Pull Request

## Context

!`git branch --show-current`
!`git log main..HEAD --oneline`
!`git diff main..HEAD --stat`

## Instructions

1. **Check CHANGELOG.md**: if it wasn't updated for these changes, update it per `.agnostic-ai/rules/changelog.md` and commit:
   ```bash
   git add CHANGELOG.md && git commit -m "chore: update changelog"
   ```

2. **Push branch**:
   ```bash
   git push -u origin HEAD
   ```

3. **Generate PR title**:
   - If `$ARGUMENTS` contains an issue number, fetch the issue title:
     ```bash
     gh issue view <number> --json title -q '.title'
     ```
   - PR title format: `<type>(<scope>): <short description>` (conventional commit style, under 70 chars)
   - Derive the type from the branch prefix (`feat/`, `fix/`, `ref/`, `perf/`, `docs/`, `test/`, `chore/`)

4. **Read `.github/PULL_REQUEST_TEMPLATE.md`** and use its **exact section headers** (including emojis) for the PR body. Do not hardcode headers: read the template file first.

5. **Create PR** using the headers from the template:
   ```bash
   gh pr create --title "<title>" --assignee @me --label "<label>" --body "$(cat <<'EOF'
   <paste exact headers from .github/PULL_REQUEST_TEMPLATE.md>

   Closes #<issue-number>
   EOF
   )"
   ```

   **Labels:** pick the single most relevant. These exist; `gh pr create` aborts on an unknown label:
   - `bug`: `fix/`
   - `enhancement`: `feat/`
   - `documentation`: `docs/`
   - `refactor`: no behavior change
   - `perf`: performance
   - `testing`: test-only changes
   - `dependencies`: dependency updates

   **Body guidelines:**
   - Focus on *what* and *why*, not implementation details
   - Use `Closes #<number>` so merging auto-closes the issue
   - Keep the entire body under 15 lines

6. **Report the PR URL** to the user.
