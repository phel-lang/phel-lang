# Agent index

Rules + CLI: [`RULES.md`](RULES.md).

## Intent map

| Intent | Recipe | Deep reference |
|--------|--------|----------------|
| Scaffold new project | [`tasks/scaffold-project.md`](tasks/scaffold-project.md) | https://phel-lang.org/documentation/getting-started/ |
| HTTP app or JSON API | [`tasks/http-app.md`](tasks/http-app.md) | `src/phel/router.phel`, `src/phel/http.phel` |
| CLI tool | [`tasks/cli-tool.md`](tasks/cli-tool.md) | https://phel-lang.org/documentation/php-interop/ |
| Add tests | [`tasks/add-tests.md`](tasks/add-tests.md) | `src/phel/test.phel`, https://phel-lang.org/documentation/testing/ |
| REPL | [`tasks/repl-workflow.md`](tasks/repl-workflow.md) | `src/phel/repl.phel` |
| Find core fn | [`tasks/use-core-lib.md`](tasks/use-core-lib.md) | `phel doc <fn>`, https://phel-lang.org/documentation/guides/cookbook/ |
| Type a fn (params + return) | [`tasks/typed-defn.md`](tasks/typed-defn.md) | https://phel-lang.org/documentation/getting-started/, https://phel-lang.org/documentation/guides/schema/ |
| Debug errors | [`tasks/debug-errors.md`](tasks/debug-errors.md) | https://phel-lang.org/documentation/guides/cookbook/ |
| Profile hot paths | [`tasks/typed-defn.md`](tasks/typed-defn.md) § Find hot paths | `phel profile <path>` |
| Async / fibers | [`tasks/async.md`](tasks/async.md) | https://phel-lang.org/documentation/language/async/, `src/phel/async.phel` |
| Memoize | [`tasks/memoize.md`](tasks/memoize.md) | `phel doc memoize`, `phel doc memoize-lru` |
| Write macros | [`tasks/write-macros.md`](tasks/write-macros.md) | https://phel-lang.org/documentation/language/macros/ |
| Common pitfalls | [`tasks/common-gotchas.md`](tasks/common-gotchas.md) | `RULES.md` § Gotchas |
| Use a PHP library | [`tasks/use-php-libs.md`](tasks/use-php-libs.md) | https://phel-lang.org/documentation/php-interop/ |
| Validate data | [`tasks/validate-with-schema.md`](tasks/validate-with-schema.md) | `src/phel/schema.phel`, https://phel-lang.org/documentation/guides/schema/ |
| Pattern match | [`tasks/pattern-match.md`](tasks/pattern-match.md) | `src/phel/match.phel`, https://phel-lang.org/documentation/language/functions-and-recursion/ |
| Lint code | — | https://phel-lang.org/documentation/tooling/cli-commands/ |
| Editor / nREPL | — | https://phel-lang.org/documentation/tooling/editor-support/, https://phel-lang.org/documentation/tooling/repl/ |
| Hot-reload | — | https://phel-lang.org/documentation/tooling/cli-commands/ |
| Syntax reference | [`quick-syntax.md`](quick-syntax.md) | https://phel-lang.org/documentation/getting-started/, https://phel-lang.org/documentation/language/reader-shortcuts/ |
| Idioms | — | https://phel-lang.org/documentation/guides/cookbook/, `examples/` |
| Call Phel from PHP | — | https://phel-lang.org/documentation/web/framework-integration/ |
| Data structures | — | https://phel-lang.org/documentation/language/data-structures/, https://phel-lang.org/documentation/language/lazy-sequences/, https://phel-lang.org/documentation/language/transducers/ |

## Examples

- `examples/todo-app/` — HTTP CRUD on `phel\router`, atom store, tests
- `examples/http-json-api/` — three JSON endpoints
- `examples/cli-wordcount/` — stdin + argv, PHP shim binary
