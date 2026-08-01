---
name: load-order
globs: resources/agents/skills/codex/**
---

Reading order for an agent working in a project that has the docs installed. This
repository is where they are written, so here they live under `resources/agents/`
and the paths below are what a user project sees after `phel agent-install`.

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map; pick `.agents/tasks/<intent>.md`
4. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
5. `src/phel/` and `docs/` only when a recipe points there
