# Agent index

Rules + CLI: [`RULES.md`](RULES.md).

## Intent map

| Intent | Recipe | Deep reference |
|--------|--------|----------------|
| Scaffold new project | [`tasks/scaffold-project.md`](tasks/scaffold-project.md) | `docs/quickstart.md` |
| HTTP app or JSON API | [`tasks/http-app.md`](tasks/http-app.md) | `src/phel/router.phel`, `src/phel/http.phel` |
| CLI tool | [`tasks/cli-tool.md`](tasks/cli-tool.md) | `docs/php-interop.md` |
| Add tests | [`tasks/add-tests.md`](tasks/add-tests.md) | `src/phel/test.phel`, `docs/mocking-guide.md` |
| REPL | [`tasks/repl-workflow.md`](tasks/repl-workflow.md) | `src/phel/repl.phel` |
| Find core fn | [`tasks/use-core-lib.md`](tasks/use-core-lib.md) | `phel doc <fn>`, `docs/patterns.md` |
| Type a fn (params + return) | [`tasks/typed-defn.md`](tasks/typed-defn.md) | `docs/quickstart.md`, `docs/schema-guide.md` |
| Debug errors | [`tasks/debug-errors.md`](tasks/debug-errors.md) | `docs/patterns.md` § Error Handling |
| Profile hot paths | [`tasks/typed-defn.md`](tasks/typed-defn.md) § Find hot paths | `phel profile <path>` |
| Async / fibers | [`tasks/async.md`](tasks/async.md) | `docs/async-guide.md`, `src/phel/async.phel` |
| Memoize | [`tasks/memoize.md`](tasks/memoize.md) | `phel doc memoize`, `phel doc memoize-lru` |
| Write macros | [`tasks/write-macros.md`](tasks/write-macros.md) | `docs/patterns.md` § Writing Macros |
| Common pitfalls | [`tasks/common-gotchas.md`](tasks/common-gotchas.md) | `RULES.md` § Gotchas |
| Use a PHP library | [`tasks/use-php-libs.md`](tasks/use-php-libs.md) | `docs/php-interop.md` |
| Validate data | [`tasks/validate-with-schema.md`](tasks/validate-with-schema.md) | `src/phel/schema.phel`, `docs/schema-guide.md` |
| Pattern match | [`tasks/pattern-match.md`](tasks/pattern-match.md) | `src/phel/match.phel`, `docs/match-guide.md` |
| Lint code | — | `docs/lint-guide.md` |
| Editor / nREPL | — | `docs/lsp-guide.md`, `docs/nrepl-guide.md` |
| Hot-reload | — | `docs/watch-guide.md` |
| Syntax reference | [`quick-syntax.md`](quick-syntax.md) | `docs/quickstart.md`, `docs/reader-shortcuts.md` |
| Idioms | — | `docs/patterns.md`, `docs/examples/` |
| Call Phel from PHP | — | `docs/framework-integration.md` |
| Data structures | — | `docs/data-structures-guide.md`, `docs/lazy-sequences.md`, `docs/transducers.md` |

## Examples

- `examples/todo-app/` — HTTP CRUD on `phel\router`, atom store, tests
- `examples/http-json-api/` — three JSON endpoints
- `examples/cli-wordcount/` — stdin + argv, PHP shim binary
