# tasks/

One recipe per workflow. Each file is a focused, copy-paste-ready answer to
"how do I do X in Phel?" — intended for AI assistants (Claude, Copilot,
Cursor, Aider, Codex, Gemini) and for humans skimming for the right idiom.

Entry point for agents: [`../index.md`](../index.md) maps user intent to the
right task here. Conventions (typed `defn`, `^:async`, `^:memoize`, error
handling) live in [`../RULES.md`](../RULES.md).

## Recipes

| File | Recipe |
|---|---|
| [`add-tests.md`](add-tests.md) | `phel\test`: `deftest`, `is`, `are`, `testing`, fixtures. |
| [`async.md`](async.md) | `phel\async` fibers for I/O concurrency (AMPHP). |
| [`cli-tool.md`](cli-tool.md) | `phel\cli` — data-driven Symfony Console wrapper. |
| [`common-gotchas.md`](common-gotchas.md) | Pitfalls; read before writing your first app. |
| [`debug-errors.md`](debug-errors.md) | Reading Phel error output and recovering. |
| [`http-app.md`](http-app.md) | `phel\http`, `phel\router`, `phel\json`, `phel\http-client`. |
| [`memoize.md`](memoize.md) | Opt-in caching via `defn` metadata. |
| [`pattern-match.md`](pattern-match.md) | `phel\match/match` for shape-based destructuring. |
| [`repl-workflow.md`](repl-workflow.md) | Interactive development (`./bin/phel repl`, built-in nREPL). |
| [`scaffold-project.md`](scaffold-project.md) | New project from scratch. |
| [`typed-defn.md`](typed-defn.md) | Typed function signatures. |
| [`use-core-lib.md`](use-core-lib.md) | Auto-required core: collections, sequences, math. |
| [`use-php-libs.md`](use-php-libs.md) | Interop with composer packages. |
| [`validate-with-schema.md`](validate-with-schema.md) | `phel-schema` for data validation. |
| [`write-macros.md`](write-macros.md) | Macros, quasiquote, hygiene rules. |

## Adding a recipe

1. New file: `tasks/<verb>.md`. Keep it terse — one screen ideally.
2. Lead with one sentence on *when* to reach for it.
3. Show the smallest runnable example.
4. Link related recipes inline.
5. Update this table and [`../index.md`](../index.md) so agents can find it.
