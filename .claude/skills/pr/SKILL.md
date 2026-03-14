---
description: Push branch and create a PR with concise description and labels
argument-hint: "[issue-number]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(git *), Bash(gh *)"
---

# Create Pull Request

## Context

!`git branch --show-current`
!`git log main..HEAD --oneline`
!`git diff main..HEAD --stat`

## Instructions

1. **Check CHANGELOG.md** — if it wasn't updated for these changes, update it now and commit:
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
   - Derive the type from the branch prefix (`feat/` → feat, `fix/` → fix, `docs/` → docs)

4. **Read `.github/PULL_REQUEST_TEMPLATE.md`** and follow its structure for the PR body.

5. **Create PR**:
   ```bash
   gh pr create --title "<title>" --assignee @me --label "<label>" --body "$(cat <<'EOF'
   ## Background
   <context for the reviewer>

   ## Goal
   <what this PR achieves, from a user perspective>

   ## Changes
   <list of individual changes>

   Closes #<issue-number>
   EOF
   )"
   ```

   **Labels:** Pick the single most relevant from:
   - `bug` — branch starts with `fix/`
   - `enhancement` — branch starts with `feat/`
   - `documentation` — branch starts with `docs/`
   - `refactoring` — code restructuring with no behavior change
   - `pure testing` — only test changes
   - `dependencies` — dependency updates

   **Body guidelines:**
   - Focus on *what* and *why*, not implementation details
   - Use `Closes #<number>` so merging auto-closes the issue
   - Keep the entire body under 15 lines

6. **Report the PR URL** to the user.
