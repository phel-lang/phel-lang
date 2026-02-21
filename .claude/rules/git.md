---
description: Git workflow, commits, branches, and PR conventions
globs: *
---

# Git Conventions

## Commits

- Always use conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
- Never mention Claude, AI, or any LLM in commit messages
- After code changes, provide a one-liner conventional commit message the user can copy/paste

## Branches

- Use prefixes: `feat/`, `fix/`, `ref/`, `docs/`

## Pull Requests

- Read and follow `.github/PULL_REQUEST_TEMPLATE.md` for PR body structure
- Assign the author as assignee: `--assignee "@me"`
- Add appropriate labels from: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`
- Push branch with `-u` flag before creating PR

## Changelog

- Update the `## Unreleased` section in `CHANGELOG.md` for any user-facing changes
- Follow existing format: `### Added`, `### Changed`, `### Fixed`, `### Removed`
