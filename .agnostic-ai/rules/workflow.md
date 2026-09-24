---
name: workflow
description: Commit, changelog, pull request, and public writing requirements.
---

# Contribution Workflow

- Use conventional commit prefixes: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`, or `perf:`.
- Keep the subject terse and imperative. Include an issue reference when applicable.
- Update `CHANGELOG.md` under `## Unreleased` for user-facing `feat:` and `fix:` changes, following `.agnostic-ai/rules/changelog.md`.
- Follow `.github/PULL_REQUEST_TEMPLATE.md` exactly, including its emoji-prefixed headings.
- Link related issues with `Closes #X` when the pull request resolves them.
- When adding changes to an open pull request, create a new commit. Do not amend or force push unless requested.
- Write public GitHub comments in the maintainer's voice. Do not mention AI or LLM tooling.
- Cut releases only with `tools/release.sh` (the `/release` skill wraps it), `--dry-run` first. The real run pushes to `main` and publishes, so get an explicit human go first. Details: `.agnostic-ai/rules/releases.md`.
