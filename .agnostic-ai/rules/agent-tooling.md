---
name: agent-tooling
description: Ownership boundaries for repository and downstream agent files.
globs:
  - .agnostic-ai/**
  - .codex/**
  - .claude/**
  - resources/agents/**
  - agnostic-ai.yaml
---

# Agent Tooling

- `.agnostic-ai/` is the source for this repository's agent config. `agnostic-ai sync` generates `.claude/`, `.codex/`, `.agents/`, `CLAUDE.md` and `AGENTS.md` from it. They are gitignored: edit the spec, never the output, then run `agnostic-ai sync`.
- Overlays (`overlays/`) hold adapter-native settings. Hook bodies live in `scripts/<adapter>/`.
- `resources/agents/` is a different product: the downstream package `phel agent-install` copies into user projects as `.agents/`. It documents building apps with Phel, not working on this repository.
- Reference another spec by its repo-root path (`.agnostic-ai/rules/changelog.md`). Generated copies land at different paths per adapter, so `.claude/...` links break for Codex.
- Put a rule where it loads. `scope: <dir>` for one directory: Claude loads it for those paths, Codex gets `<dir>/AGENTS.md`. `globs:` narrows only Claude; Codex still inlines the rule in the root `AGENTS.md`. Unscoped only for policy every task needs, and short.
- Keep rules flat in `rules/`: a subdirectory becomes an implicit scope that overrides `scope:`. Never pair `scope:` with `globs:` outside it; Claude drops the rule.
- `agnostic-ai` ignores unknown frontmatter keys silently. An adapter-native key goes under `x-claude:` or `x-codex:`.
