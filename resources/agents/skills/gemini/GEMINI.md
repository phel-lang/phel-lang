# GEMINI.md: Phel project

Phel is a Lisp dialect that compiles to PHP.

## Load order

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map; pick `.agents/tasks/<intent>.md`
4. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
5. `vendor/phel-lang/phel-lang/src/phel/` and `vendor/phel-lang/phel-lang/docs/` only when a recipe points there

## Before suggesting code

- Verify fn names with `./vendor/bin/phel doc <fn> --format=json` or in `vendor/phel-lang/phel-lang/src/phel/core/`. Never invent.
- Check loop after every edit: `./vendor/bin/phel lint <file> --format=json`, then `./vendor/bin/phel explain <PHELnnn>` for an unknown code, then `./vendor/bin/phel doc <fn> --format=json` for an unverified fn. Details in `.agents/RULES.md`.
- Hot or public `defn`: add `:tag` (`^int`, `^"?int"`, `^Foo.Bar`, `^{:tag "..."}`). See `.agents/tasks/typed-defn.md`.
- Opt-in defn metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- `phel profile <path>` locates hot fns before tagging.
- Namespace separator: prefer `.` (`app.main`); `\` still parses but is deprecated.

Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`.
