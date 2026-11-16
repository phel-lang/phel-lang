---
name: workflow
description: Commits, changelog, pull requests, releases and public text.
---

# Workflow

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`, `perf:`. Terse imperative subject, issue ref when one applies.
- A user-facing `feat:`, `fix:` or `perf:` adds a `CHANGELOG.md` entry under `## Unreleased`, per `.agnostic-ai/rules/changelog.md`.
- PR bodies follow `.github/PULL_REQUEST_TEMPLATE.md` exactly, emoji headings included. `Closes #X` only when the PR resolves the issue.
- On an open PR, add a new commit. Amend or force push only when asked.
- Write public GitHub text in the maintainer's voice. Mention AI tooling only when it is the subject; no Co-authored-by trailer or "Generated with" line.
- Release only through `tools/release.sh`: `--dry-run` first, a human go before the real run. Details: `.agnostic-ai/rules/releases.md`.
