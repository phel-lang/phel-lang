# Copilot: Phel project

Phel is a Lisp that compiles to PHP. For `.phel` files and `phel-config.php`:

- Hard rules, syntax, CLI: `.agents/RULES.md`
- Task recipes: `.agents/tasks/`
- Typed `defn` (`:tag` on params + return, `^:async`, `^:memoize`, `^{:memoize-lru N}`): `.agents/tasks/typed-defn.md`
- Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`
- Deep reference: `docs/`, `src/phel/`

Never invent fn names. Confirm against `src/phel/core/` or `./vendor/bin/phel doc <fn>`.

Prefer `.` ns separator (`app.main`); `\` still parses but is deprecated.
