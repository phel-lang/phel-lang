---
name: project-structure-module-organization
description: Where the compiler, stdlib, tests and tooling live.
---

- `src/php/`: compiler and runtime, PSR-4 `Phel\`, one Gacela module per directory. Shared module conventions: `.agnostic-ai/rules/module-map.md`.
- `src/phel/`: the standard library in Phel. `src/Phel.php`: the public `\Phel` embedding class.
- Tests: `tests/php/{Unit,Integration,Benchmark}`, `tests/phel/`. CLI: `bin/phel`. PHAR build: `build/`. Maintainer scripts: `tools/`.
