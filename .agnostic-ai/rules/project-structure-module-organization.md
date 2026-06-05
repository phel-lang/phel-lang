---
name: project-structure-module-organization
---

Phel is a Lisp that compiles to PHP, inspired by Clojure. The codebase has two source trees:

- **`src/php/`** — PHP runtime and compiler (PSR-4: `Phel\`). Key modules: `Lang` (persistent data types), `Compiler` (lex/parse/analyze/emit pipeline), `Run` (namespace execution and REPL), `Console` (Symfony CLI), `Command` (shared command helpers), `Build` (build orchestration), `Config` (configuration), and `Shared` (constants and facades).
- **`src/phel/`** — Core library written in Phel itself: `core`, `string`, `html`, `http`, `json`, `test`, `repl`, `walk`, `pprint`, `reflect`, `mock`.

Entry points live in `Phel.php` and `bin/`, distributable artifacts and scripts sit under `build/`, documentation and examples reside in `docs/`, and `tests/php` is split into `Unit`, `Integration`, and `Benchmark`; Phel-level tests are in `tests/phel/`. Temporary outputs land in `data/` or `var/`.
