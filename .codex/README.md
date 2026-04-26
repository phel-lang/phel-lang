# Codex project config

This directory contains Codex-native repo-maintenance configuration for Phel.

| Path | Purpose |
|------|---------|
| `config.toml` | Project Codex settings, feature flags, and subagent limits. |
| `hooks.json` | Codex hook wiring. |
| `hooks/` | Codex hook scripts. These use Codex hook JSON, not Claude hook JSON. |
| `rules/` | Codex exec-policy rules for approval and forbidden command prefixes. |
| `agents/` | Codex custom subagents in TOML format. |
| `skills/` | Codex skills shared by contributors working in this repository. |

Shared repository policy belongs in `AGENTS.md`. Claude Code-specific material belongs in `.claude/`. Downstream Phel
user assets belong in `resources/agents/`.

To install the shared project skills into a local Codex profile when repo-local skills are not auto-loaded:

```bash
mkdir -p ~/.codex/skills
cp -R .codex/skills/* ~/.codex/skills/
```

To start autonomous GitHub issue processing from Codex, invoke the repo skill:

```text
Use $watch-gh-issues
```
