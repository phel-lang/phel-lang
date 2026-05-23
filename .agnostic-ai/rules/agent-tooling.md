---
name: agent-tooling
---

Repo-maintenance agent files are adapter-native:

- `.codex/` contains Codex config, hooks, exec rules, and custom subagents.
- `.claude/` contains Claude Code settings, hooks, skills, agents, and scoped rules.
- `resources/agents/` contains downstream assets installed into user projects as `.agents/`.

Avoid duplicating long-form guidance across adapter folders. Keep durable repository policy here in `AGENTS.md`, keep
tool-specific mechanics inside the matching adapter directory, and update both adapters only when the behavior truly
differs by tool.
