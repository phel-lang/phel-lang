# Create Pull Request

Push branch and create a PR with a concise description.

## Arguments
- `$ARGUMENTS` - Issue reference (optional, e.g., `#42` or `42`). If provided, the PR will be linked to this issue.

## Instructions

1. **Get current branch and commits**:
   ```bash
   git branch --show-current
   git log main..HEAD --oneline
   git diff main..HEAD --stat
   ```

2. **Check CHANGELOG.md** — if it wasn't updated for these changes, update it now and commit:
   ```bash
   git add CHANGELOG.md && git commit -m "chore: update changelog"
   ```

3. **Push branch**:
   ```bash
   git push -u origin HEAD
   ```

4. **Generate PR title**:
   - If `$ARGUMENTS` contains an issue number, fetch the issue title:
     ```bash
     gh issue view <number> --json title -q '.title'
     ```
   - PR title format: `<type>(<scope>): <short description>` (conventional commit style, under 70 chars)
   - Derive the type from the branch prefix (`feat/` → feat, `fix/` → fix, `docs/` → docs)

5. **Read `.github/PULL_REQUEST_TEMPLATE.md`** and follow its structure for the PR body.

6. **Create PR**:
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
   - No file lists, no class names, no code snippets in the summary
   - Use `Closes #<number>` so merging auto-closes the issue
   - Keep the entire body under 15 lines

7. **Report the PR URL** to the user.

## Example Usage

```
/pr
/pr #42
/pr 15
```
