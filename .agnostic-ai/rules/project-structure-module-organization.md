---
name: project-structure-module-organization
description: Where the PHP compiler, the Phel stdlib, tests and build output live.
---

- `src/php/`: PHP runtime and compiler, PSR-4 `Phel\`. One module per directory (`Compiler`, `Lang`, `Run`, `Console`, `Build`, `Shared`, ...), each with a `CLAUDE.md` for its public API and constraints. `src/php/CLAUDE.md` holds the module map and the Gacela conventions.
- `src/phel/`: the standard library written in Phel (`core`, `test`, `string`, `json`, `http`, ...).
- `tests/php/{Unit,Integration,Benchmark}` and `tests/phel/`.
- Entry points: `Phel.php`, `bin/`. PHAR build: `build/`. Maintainer scripts: `tools/`. Contributor docs: `docs/`. Scratch output: `data/`, `var/`.
