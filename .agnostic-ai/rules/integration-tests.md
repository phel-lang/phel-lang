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

REPL test fixtures live in `tests/php/Integration/Run/Command/Repl/Fixtures/` (no core lib) and
`.../FixturesWithCoreLib/` (core lib preloaded). They use no markers at all: a fixture is a literal
REPL transcript, and the whole file is the expected output.

```
user:1> (php/+ 1 1)
2
```

`ReplCommandTest::getInputs()` recovers the inputs by matching each line against the prompt regex
`(?<prompt>(?:[\w\\.-]+|\.{4}):\d+> ?)(?<phel_code>.+)?`, so:

- Every input line must carry a real prompt: `<ns>:<n>> ` for a new form, `....:<n>> ` for a
  continuation line inside an unfinished form.
- Everything without a prompt is expected output (results, error reports, code snippets).
- Comparison is `assertSame` on the whole transcript after `trim()`, so blank leading/trailing lines
  are ignored but interior whitespace is significant.

## Running

```bash
composer test-compiler --testsuite=integration
./vendor/bin/phpunit --testsuite=integration --filter=ClassName
```
