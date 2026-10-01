---
name: agent-tooling
description: Ownership boundaries for repository and downstream agent files.
# Claude only: agnostic-ai.yaml is a root file and .codex/.claude are generated, so Codex would load this in every session. Its developer_instructions point here.
targets: [claude]
globs:
  - .agnostic-ai/**
  - .codex/**
  - .claude/**
  - resources/agents/**
  - agnostic-ai.yaml
---

# Agent Tooling

- `.agnostic-ai/` is the source for this repository's agent config. `agnostic-ai sync` generates `.claude/`, `.codex/`, `.agents/`, `CLAUDE.md` and `AGENTS.md` from it. They are gitignored: edit the spec, never the output, then run `agnostic-ai sync`.
- `settings/` holds portable settings such as protected paths; `overlays/` holds adapter-native settings. Hook scripts live in `scripts/` and serve both tools; read edited files with `agnostic-ai hook paths`, and test a hook with `agnostic-ai hook run <name>`.
- `resources/agents/` is a different product: the downstream package `phel agent-install` copies into user projects as `.agents/`. It documents building apps with Phel, not working on this repository.
- Reference another spec by its repo-root path (`.agnostic-ai/rules/changelog.md`). Generated copies land at different paths per adapter, so `.claude/...` links break for Codex.
- Put a rule where it loads. `scope:` and `globs:` form a union: Claude gets every entry in `paths`, Codex gets an `AGENTS.md` in each whole subtree (`dir/**`). A filename filter (`*.md`, `CHANGELOG.md`) cannot nest for Codex: keep that rule Claude only with a pointer, or load it from the root on purpose with `x-codex: {alwaysApply: true}`. Unscoped only for policy every task needs, and short.
- Keep rules flat in `rules/`: a folder that names an existing directory becomes the rule's scope. Write `scope:` explicitly instead.
- `agnostic-ai` ignores unknown frontmatter keys silently. An adapter-native key goes under `x-claude:` or `x-codex:`.
