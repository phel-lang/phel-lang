# GEMINI.md: Phel project

Phel is a Lisp dialect that compiles to PHP.

## Load order

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map; pick `.agents/tasks/<intent>.md`
4. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
5. `src/phel/` and `docs/` only when a recipe points there

## Before suggesting code

- Verify fn names in `src/phel/core/` or `./vendor/bin/phel doc <fn>`. Never invent.
- Hot or public `defn`: add `:tag` (`^int`, `^"?int"`, `^"\\Foo\\Bar"`, `^{:tag "..."}`). See `.agents/tasks/typed-defn.md`.
- Opt-in defn metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- `phel profile <path>` locates hot fns before tagging.
- Namespace separator: prefer `.` (`app.main`); `\` still parses but is deprecated.

Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`.
