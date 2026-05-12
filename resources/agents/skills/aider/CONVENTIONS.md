# Aider conventions: Phel

Phel is a Lisp that compiles to PHP. Pass to Aider via `--read CONVENTIONS.md` or add to `.aider.conf.yml` under `read`.

## Load order

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map; pick `.agents/tasks/<intent>.md`
4. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
5. `docs/`, `src/phel/` — deep reference

## Before suggesting code

- Verify fn names in `src/phel/core/` or `./vendor/bin/phel doc <fn>`. Never invent.
- Typed `defn` (`:tag` on params + return, `^:async`, `^:memoize`, `^{:memoize-lru N}`): `.agents/tasks/typed-defn.md`.
- Namespace separator: prefer `.` (`app.main`); `\` still parses but is deprecated.

Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`.

## Commits

Conventional (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). No AI or LLM references in messages.
