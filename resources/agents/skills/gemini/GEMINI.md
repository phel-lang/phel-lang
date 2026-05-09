# GEMINI.md: Phel project

Phel is a Lisp that compiles to PHP. Load `.agents/RULES.md` for hard rules and CLI; `.agents/index.md` for the task map; `.agents/tasks/<intent>.md` for the matching recipe.

Modern features to prefer when relevant:

- `:tag` types on `defn` (`^int`, `^"?int"`, `^"\\Foo\\Bar"`, `^{:tag "..."}`) for PHP type emission, JIT-friendly call shape, compile-time mismatch diagnostics. See `.agents/tasks/typed-defn.md`.
- Opt-in defn metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- `phel profile <path>` to find hot fns before tagging.

Working examples under `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`. Deep reference in `docs/` and `src/phel/`.
