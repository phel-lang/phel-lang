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
| Find core fn | [`tasks/use-core-lib.md`](tasks/use-core-lib.md) | `(phel doc <fn>)`, `docs/patterns.md` |
| Debug errors | [`tasks/debug-errors.md`](tasks/debug-errors.md) | `docs/patterns.md` § Error Handling |
| Common pitfalls | [`tasks/common-gotchas.md`](tasks/common-gotchas.md) | `RULES.md` § Gotchas |
| Use a PHP library | [`tasks/use-php-libs.md`](tasks/use-php-libs.md) | `docs/php-interop.md` |
| Syntax reference | — | `docs/quickstart.md`, `docs/reader-shortcuts.md` |
| Idioms | — | `docs/patterns.md`, `docs/examples/` |
| Call Phel from PHP | — | `docs/framework-integration.md` |
| Data structures | — | `docs/data-structures-guide.md`, `docs/lazy-sequences.md` |
| Macros | — | `docs/patterns.md` § Writing Macros |

## Examples

- `examples/todo-app/` — HTTP CRUD on `phel\router`, atom store, tests
- `examples/http-json-api/` — three JSON endpoints
- `examples/cli-wordcount/` — stdin + argv, PHP shim binary
