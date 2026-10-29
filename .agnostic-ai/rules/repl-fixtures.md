---
name: repl-fixtures
description: Prompt and output rules for REPL transcript fixtures.
scope: tests/php/Integration/Run/Command/Repl
---

# REPL Fixtures

`tests/php/Integration/Run/Command/Repl/Fixtures/` runs without core; `tests/php/Integration/Run/Command/Repl/FixturesWithCoreLib/` preloads it. A fixture is a literal REPL transcript, with no `--PHEL--` or `--PHP--` markers:

```
user:1> (php/+ 1 1)
2
```

`ReplCommandTest::getInputs()` matches each line with `(?<prompt>(?:[\w\\.-]+|\.{4}):\d+> ?)(?<phel_code>.+)?`:

- Every input line needs `<ns>:<n>> ` for a new form or `....:<n>> ` for a continuation.
- Lines without prompts are expected output: results, errors or code snippets.
- Comparison uses `assertSame` after `trim()`: leading and trailing blank lines are ignored; interior whitespace matters.

Run the affected transcript with `./vendor/bin/phpunit --testsuite=integration --filter=<fixture-stem>`; use `ReplCommandTest` for changes shared by the transcript loader.
