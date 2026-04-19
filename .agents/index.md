# Agent Index

Map user intent → docs to load.

## User wants to...

| Intent | Read first | Then |
|--------|-----------|------|
| Scaffold new Phel project | [`tasks/scaffold-project.md`](tasks/scaffold-project.md) | `docs/quickstart.md` |
| Build HTTP app / API | [`tasks/http-app.md`](tasks/http-app.md) | `docs/framework-integration.md`, `docs/php-interop.md` |
| Learn syntax fast | `docs/quickstart.md` | `docs/reader-shortcuts.md` |
| Write idiomatic code | `docs/patterns.md` | `docs/examples/` |
| Call PHP from Phel | `docs/php-interop.md` | — |
| Call Phel from PHP | `docs/framework-integration.md` | `docs/php-interop.md` (§ Calling Phel from PHP) |
| Add tests | `docs/quickstart.md` (§ Testing) | `docs/mocking-guide.md` |
| Use REPL effectively | `docs/quickstart.md` (§ REPL Workflow) | — |
| Understand data structures | `docs/data-structures-guide.md` | `docs/lazy-sequences.md` |
| Migrate from Clojure | `docs/clojure-migration.md` | `docs/php-interop.md` (§ Tips for Clojure Developers) |
| Write macros | `docs/patterns.md` (§ Writing Macros) | — |

## Core workflow (every task)

1. **Scaffold or verify layout**: `phel init` or inspect existing `phel-config.php`.
2. **Explore unknowns in REPL**: `./vendor/bin/phel repl` → `(doc <fn>)`, `(require 'ns)`, `(in-ns 'ns)`.
3. **Write code** under `src/phel/<ns>.phel` (default layout) or `src/<ns>.phel` (`--flat`).
4. **Test** with `phel\test`: `deftest`, `is`. Run `./vendor/bin/phel test`.
5. **Run** with `./vendor/bin/phel run src/...` or web entry point.

## Hard rules

- Never invent function names. Verify with `(doc <fn>)` or grep `src/phel/core/**`.
- Immutability: `(conj v x)` returns new vec. `v` unchanged. Rebind with `def` or `let`.
- Top-level side effects break `phel build`. Guard with `(when-not *build-mode* ...)`.
- PHP call prefix is `php/` not `.` or `..`.
- Collection ops: `->>` (thread-last). Object/map ops: `->` (thread-first).
- Falsy values are only `false` and `nil`. `0`, `""`, `[]` are truthy.

## Commands reference

| Task | Command |
|------|---------|
| Scaffold project | `./vendor/bin/phel init [name] [--minimal|--flat]` |
| Run script | `./vendor/bin/phel run <file.phel>` |
| Eval expression | `./vendor/bin/phel eval '(+ 1 2)'` or `echo '(...)' \| phel eval -` |
| REPL | `./vendor/bin/phel repl` |
| Run tests | `./vendor/bin/phel test [path]` |
| Build for prod | `./vendor/bin/phel build` |
| Doc lookup | `./vendor/bin/phel doc <fn>` |
| Format | `./vendor/bin/phel format <file>` |
| List namespaces | `./vendor/bin/phel ns` |
