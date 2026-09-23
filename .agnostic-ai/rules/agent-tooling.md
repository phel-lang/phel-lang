---
name: agent-tooling
description: Ownership boundaries for repository and downstream agent files.
globs: .agnostic-ai/**,.codex/**,.claude/**,resources/agents/**,agnostic-ai.yaml
---

# Agent Tooling

- `.agnostic-ai/` is the source for this repository's agent config. `agnostic-ai sync` generates `.claude/`, `.codex/`, `.agents/`, `CLAUDE.md` and `AGENTS.md` from it. They are gitignored: edit the spec, never the output, then run `agnostic-ai sync`.
- Overlays (`overlays/`) hold adapter-native settings. Hook bodies live in `scripts/<adapter>/`.
- `resources/agents/` is a different product: the downstream package `phel agent-install` copies into user projects as `.agents/`. It documents building apps with Phel, not working on this repository.
- Reference another spec by its repo-root path (`.agnostic-ai/rules/changelog.md`). Generated copies land at different paths per adapter, so `.claude/...` links break for Codex.
- Put a rule where it loads: a `globs:` scoped rule for file-specific conventions, an unscoped rule only for policy every task needs. Codex inlines every rule, so keep each one short.
- `agnostic-ai` ignores unknown frontmatter keys silently. An adapter-native key goes under `x-claude:` or `x-codex:`.
