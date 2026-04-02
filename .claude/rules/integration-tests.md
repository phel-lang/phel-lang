---
description: Integration test fixture format and conventions
globs: tests/php/Integration/**
---

# Integration Test Fixtures

## `.test` File Format

Integration test fixtures use a two-section format separated by markers:

```
--PHEL--
(def x 1)
--PHP--
\Phel::addDefinition(
  "user",
  "x",
  1,
  ...
);
```

- `--PHEL--` section: Phel source code input
- `--PHP--` section: expected compiled PHP output (exact match)

## Conventions

- One fixture per behavior — name files descriptively: `fn-variadic.test`, `try-one-catch.test`
- Fixtures live in `tests/php/Integration/Fixtures/<Category>/`
- Categories mirror language constructs: `Def/`, `Fn/`, `Let/`, `Try/`, `Call/`, etc.
- PHP output uses `\Phel::` static helpers (`addDefinition`, `map`, `keyword`, `vector`, etc.)
- Source locations are embedded in metadata — update line/column if you change the Phel input

## REPL Fixtures

REPL test fixtures in `tests/php/Integration/Run/Command/Repl/Fixtures/` use a different format with `--INPUT--` and `--EXPECT--` markers for interactive session testing.

## Running

```bash
composer test-compiler --testsuite=integration
./vendor/bin/phpunit --testsuite=integration --filter=ClassName
```
