---
name: docs-placement
description: Which Markdown file owns which kind of content.
globs:
  - '*.md'
  - docs/**
  - .github/*.md
  - .agnostic-ai/rules/module-*.md
  - resources/agents/**
---

# Where Docs Go

Put content in the file whose reader needs it. Link instead of copying.

| File | Reader | Holds |
|---|---|---|
| `README.md` | Phel users | What Phel is, install, first steps, links. No contributor setup. |
| `CHANGELOG.md` | Phel users | Release notes, per `.agnostic-ai/rules/changelog.md`. |
| `.github/CONTRIBUTING.md` | Human contributors | Setup, commands, tests, commits, changelog, AI tooling setup. |
| `.github/RELEASE.md` | Maintainers | How a release is cut. |
| `docs/` | Contributors | Spec, stability policy, ADRs, internals, CLI reference, migrations. User guides live on phel-lang.org. |
| `docs/adr/` | Contributors | One record per decision, per `.agnostic-ai/rules/architecture-decisions.md`. |
| `.agnostic-ai/rules/module-*.md` | Agents and contributors | Module map, public API, dependencies, constraints. Each is scoped to its module directory. |
| `.agnostic-ai/` | Agents | Repository policy, skills, agents. Generates `AGENTS.md` and the root `CLAUDE.md`. |
| `resources/agents/` | Agents in user projects | How to build apps with Phel. Shipped by `phel agent-install`. |

- Never link to `AGENTS.md`, the root `CLAUDE.md`, `.claude/` or `.codex/` from tracked docs. They are generated and gitignored, so the link is dead on GitHub.
- A user-facing guide belongs on phel-lang.org. Link it from `README.md` or `docs/README.md`.
