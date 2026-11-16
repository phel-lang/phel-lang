---
name: agent-tooling
description: Ownership boundaries for repository and downstream agent files.
scope: .agnostic-ai
---

# Agent Tooling

- `.agnostic-ai/` is the source for this repository's agent config. `agnostic-ai sync` generates `.claude/`, `.codex/`, `.agents/`, `CLAUDE.md` and `AGENTS.md` from it. They are gitignored: edit the spec, never the output, then run `agnostic-ai sync`.
- `settings/` holds portable settings such as protected paths; `overlays/` holds adapter-native settings. Hook scripts live in `scripts/` and serve both tools; read edited files with `agnostic-ai hook paths`, and test a hook with `agnostic-ai hook run <name>`.
- `resources/agents/` is a different product: the downstream package `phel agent-install` copies into user projects as `.agents/`. It documents building apps with Phel, not working on this repository.
- Reference another spec by its repo-root path (`.agnostic-ai/rules/changelog.md`). Generated copies land at different paths per adapter, so `.claude/...` links break for Codex.
- Put a rule where it loads. `scope:` and `globs:` form a union: Claude gets every entry in `paths`, Codex gets an `AGENTS.md` in each whole subtree (`dir/**`). A filename filter (`*.md`, `CHANGELOG.md`) cannot nest for Codex: keep that rule Claude only with a pointer, or load it from the root on purpose with `x-codex: {alwaysApply: true}`. Unscoped only for policy every task needs, and short.
- Keep rules flat in `rules/`: a folder that names an existing directory becomes the rule's scope. Write `scope:` explicitly instead.
- `agnostic-ai` ignores unknown frontmatter keys silently. An adapter-native key goes under `x-claude:` or `x-codex:`.
- Keep procedures and descriptions shared. Use portable fields first: agent `can`, skill `allowed-tools`, and shared `maxTurns`. Keep `Glob` and `Grep` aliases when needed, because `read` grants only file reading. Add a target override only for a useful native capability or a documented adapter limit. Put tool-wide settings in overlays rather than repeating them on specs.
- Record unsupported portable restrictions in `agnostic-ai.yaml` coverage accepts. Codex cannot enforce per-agent tool lists, skill tool grants or per-agent turn caps; shared body instructions state task limits within the parent session permissions. Claude-specific memory and Codex agent names remain native overrides.
- Gate after edits: `agnostic-ai validate`, `agnostic-ai lint --strict`, `agnostic-ai sync`, then `agnostic-ai sync --check`.
- `agnostic-ai explain --context --target <claude|codex>` shows startup cost: keep module details scoped and task procedures in skills. After a scope change, `agnostic-ai explain --file <path> --target <claude|codex>` shows which rules apply to a file. It reports configuration, not a live session; Codex must still read each ancestor `AGENTS.md`.
