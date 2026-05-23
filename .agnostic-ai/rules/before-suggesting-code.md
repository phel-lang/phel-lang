---
name: before-suggesting-code
globs: resources/agents/skills/codex/**
---

- Verify fn names in `src/phel/core/` or `./vendor/bin/phel doc <fn>`. Never invent.
- Hot or public `defn`: add `:tag` to params + return. See `.agents/tasks/typed-defn.md`.
- Opt-in defn metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- `phel profile <path>` locates hot fns before tagging.
- Namespace separator: prefer `.` (`app.main`); `\` still parses but is deprecated.
