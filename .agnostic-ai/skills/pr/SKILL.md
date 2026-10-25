---
description: Push the branch and open a PR from the template.
argument-hint: "[issue-number]"
allowed-tools:
  - "read"
  - "write"
  - "edit"
  - "shell(git *)"
  - "shell(gh *)"
  - "shell(composer *)"
  - "shell(agnostic-ai *)"
# Model-invocable on purpose: the issue workflow opens its PR through this skill.
---

# Create Pull Request

## Context

Run first: `git branch --show-current`, `git log main..HEAD --oneline`, `git diff main..HEAD --stat`.

## Instructions

1. **Prepare**: review the complete diff against the PR's base and the working tree. Preserve unrelated work. Add a `CHANGELOG.md` entry only for user-facing changes, per `.agnostic-ai/rules/changelog.md`.

2. **Review, gate and commit**: have `phel-reviewer` review the diff and resolve actionable findings before the final gate. Follow `.agnostic-ai/skills/commit/SKILL.md` for pending changes: stage the complete intended change, pass the applicable gate, then commit. Let a successful pre-commit hook supply the gate only when staged PHP or Phel files trigger it. If there is nothing to commit, run the applicable gate in `.agnostic-ai/rules/build-test-and-development-commands.md` or reuse a successful run for the same change set. Rerun affected checks after fixes. Do not commit or push with failing checks or unresolved defects.

3. **Push branch**:
   ```bash
   git push -u origin HEAD
   ```

4. **Generate PR title**:
   - If the argument contains an issue number, fetch the issue title:
     ```bash
     gh issue view <number> --json title -q '.title'
     ```
   - PR title format: `<type>(<scope>): <short description>` (conventional commit style, under 70 chars)
   - Derive the type from the branch prefix (`feat/`, `fix/`, `ref/`, `perf/`, `docs/`, `test/`, `chore/`)

5. **Read `.github/PULL_REQUEST_TEMPLATE.md`** and use its **exact section headers** (including emojis) for the PR body. Do not hardcode headers: read the template file first.

6. **Create PR** using the headers from the template. Write the body to a temporary file with real newlines, then pass its path:
   ```bash
   gh pr create --title "<title>" --assignee @me --label "<label>" --body-file <body-file>
   ```

   **Labels:** query `gh label list` and pick the single most relevant available label. Suggested mapping:
   - `bug`: `fix/`
   - `enhancement`: `feat/`
   - `documentation`: `docs/`
   - `refactor`: no behavior change
   - `perf`: performance
   - `testing`: test-only changes
   - `dependencies`: dependency updates

   **Body guidelines:**
   - Focus on *what* and *why*, not implementation details
   - Use `Closes #<number>` only when the PR resolves that issue
   - Include validation results and any remaining limits; keep it concise

7. **Report the PR URL** and validation results to the user.
