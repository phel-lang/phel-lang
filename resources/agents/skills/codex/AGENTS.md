# AGENTS.md: Phel project

Phel is a Lisp dialect that compiles to PHP. This file follows the AGENTS.md convention and covers tools that honor it (Codex, Aider, generic LLMs).

## Load order

1. `.agents/RULES.md` — hard rules, CLI cheatsheet, workflow
2. `.agents/index.md` — task map
3. `.agents/tasks/<intent>.md` — recipe for the current task
4. `src/phel/` and `docs/` only when a recipe points there

## Modern features to prefer

- `:tag` types on `defn` params + return for PHP type emission, JIT-friendly call shape, compile-time mismatch diagnostics. See `.agents/tasks/typed-defn.md`.
- Opt-in defn metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- `phel profile <path>` to locate hot fns before tagging.

## Working examples

`.agents/examples/{todo-app, http-json-api, cli-wordcount}/`; copy, adapt, run.

## Commit conventions

Conventional commits (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). No AI or LLM references in messages.
