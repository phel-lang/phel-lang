---
name: repl-fixtures
description: 'Load when adding or changing a REPL transcript fixture: prompt syntax and output matching.'
scope: tests/php/Integration/Run/Command/Repl
---

# REPL Fixtures

`Fixtures/` runs without core; `FixturesWithCoreLib/` preloads it. A fixture is a literal REPL transcript, with no `--PHEL--` or `--PHP--` markers:

```
user:1> (php/+ 1 1)
2
```

`ReplCommandTest::getInputs()` matches each line with `(?<prompt>(?:[\w\\.-]+|\.{4}):\d+> ?)(?<phel_code>.+)?`:

- An input line starts with `<ns>:<n>> ` for a new form or `....:<n>> ` for a continuation.
- Any other line is expected output: results, errors or code snippets.
- Comparison is `assertSame` after `trim()`: leading and trailing blank lines are ignored, interior whitespace matters.

Run one transcript with `./vendor/bin/phpunit --testsuite=integration --filter=<fixture-stem>`; run `ReplCommandTest` when changing the loader.
