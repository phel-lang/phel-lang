# Claude Code project config

This directory contains Claude Code-native repo-maintenance configuration for Phel.

| Path | Purpose |
|------|---------|
| `CLAUDE.md` | Claude Code project entrypoint. |
| `settings.json` | Claude Code permissions, hooks, and status line config. |
| `settings.local.json` | Local Claude Code allowances currently tracked for this repo. |
| `hooks/` | Claude Code hook scripts. These consume Claude hook JSON. |
| `agents/` | Claude Code subagent prompts in Markdown format. |
| `skills/` | Claude Code slash-command and workflow skills. |
| `rules/` | Claude Code scoped rules for repo-maintenance work. |

Shared repository policy belongs in `AGENTS.md`. Codex-native config belongs in `.codex/`. Downstream Phel user assets
belong in `.agents/`.
