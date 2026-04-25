# Codex project config

This directory contains Codex-native repo-maintenance configuration for Phel.

| Path | Purpose |
|------|---------|
| `config.toml` | Project Codex settings, feature flags, and subagent limits. |
| `hooks.json` | Codex hook wiring. |
| `hooks/` | Codex hook scripts. These use Codex hook JSON, not Claude hook JSON. |
| `rules/` | Codex exec-policy rules for approval and forbidden command prefixes. |
| `agents/` | Codex custom subagents in TOML format. |

Shared repository policy belongs in `AGENTS.md`. Claude Code-specific material belongs in `.claude/`. Repo-local
agent assets belong in `.agents/`. Downstream Phel user assets belong in `resources/agents/`.
