# Copilot: Phel project

Phel is a Lisp that compiles to PHP. For `.phel` files and `phel-config.php`:

## Load order

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map; pick `.agents/tasks/<intent>.md`
4. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
5. `vendor/phel-lang/phel-lang/docs/`, `vendor/phel-lang/phel-lang/src/phel/`: deep reference

## Before suggesting code

- Verify fn names with `./vendor/bin/phel doc <fn> --format=json` or in `vendor/phel-lang/phel-lang/src/phel/core/`. Never invent.
- Check loop after every edit: `./vendor/bin/phel lint <file> --format=json`, then `./vendor/bin/phel explain <PHELnnn>` for an unknown code, then `./vendor/bin/phel doc <fn> --format=json` for an unverified fn. Details in `.agents/RULES.md`.
- Typed `defn` (`:tag` on params + return, `^:async`, `^:memoize`, `^{:memoize-lru N}`): `.agents/tasks/typed-defn.md`.
- Namespace separator: prefer `.` (`app.main`); `\` still parses but is deprecated.

Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`.
