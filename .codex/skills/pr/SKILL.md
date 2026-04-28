---
name: pr
description: Push the current branch and create a GitHub pull request. Use when Codex is asked to open a PR, prepare a PR body, apply the repository PR template, label a PR, or report the PR URL.
---

# Create Pull Request

## Workflow

1. Inspect branch context:
   ```bash
   git branch --show-current
   git log main..HEAD --oneline
   git diff main..HEAD --stat
   ```

2. Check whether `CHANGELOG.md` is required. Update and commit it only for user-facing changes that warrant release notes.

3. Push the branch:
   ```bash
   git push -u origin HEAD
   ```

4. Generate a concise PR title:
   - conventional style, under 70 characters
   - derive type from branch prefix when useful: `feat/`, `fix/`, `docs/`, `ref/`, `test/`, `chore/`
   - if an issue number is provided, fetch the issue title with `gh issue view <number> --json title -q '.title'`

5. Read `.github/PULL_REQUEST_TEMPLATE.md` and use its exact section headers, including emojis.

6. Pick the most relevant label when labels are available:
   - `bug` for fixes
   - `enhancement` for features
   - `documentation` for docs
   - `refactoring` for behavior-preserving restructuring
   - `pure testing` for test-only changes
   - `dependencies` for dependency updates

7. Create the PR:
   ```bash
   gh pr create --title "<title>" --assignee @me --label "<label>" --body-file <body-file>
   ```

8. Report the PR URL.

## Body Guidance

- Focus on what and why.
- Keep the body concise.
- Include `Closes #<number>` only when the PR fully resolves the issue.
