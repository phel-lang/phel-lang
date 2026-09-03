---
name: project-structure-module-organization
---

Phel is a Lisp that compiles to PHP, inspired by Clojure. The codebase has two source trees:

- **`src/php/`** — PHP runtime and compiler (PSR-4: `Phel\`). Key modules: `Lang` (persistent data types), `Compiler` (lex/parse/analyze/emit pipeline), `Run` (namespace execution and REPL), `Console` (Symfony CLI), `Command` (shared command helpers), `Build` (build orchestration), `Config` (configuration), and `Shared` (constants and facades). These are only the key ones — every module directory under `src/php/` (also `Api`, `Balance`, `Fiber`, `Filesystem`, `Formatter`, `HttpClient`, `Interop`, `Lint`, `Lsp`, `Mutate`, `Nrepl`, `Profile`, `Watch`) has its own `CLAUDE.md` documenting its public API and constraints.
- **`src/phel/`** — Core library written in Phel itself: `core`, plus `ai`, `async`, `base64`, `bench`, `cli`, `edn`, `html`, `http`, `http-client`, `json`, `match`, `mock`, `pprint`, `reader`, `reflect`, `repl`, `router`, `schema`, `string`, `test`, `trace`, `transit`, `walk`, `watch`.

Entry points live in `Phel.php` and `bin/`, distributable artifacts and scripts sit under `build/`, documentation and examples reside in `docs/`, and `tests/php` is split into `Unit`, `Integration`, and `Benchmark`; Phel-level tests are in `tests/phel/`. Temporary outputs land in `data/` or `var/`.
