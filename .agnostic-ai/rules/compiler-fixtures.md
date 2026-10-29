---
name: compiler-fixtures
description: Exact Phel input and PHP output for compiler fixtures.
scope: tests/php/Integration/Fixtures
---

# Compiler Fixtures

Each `.test` file has two sections:

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

- `--PHEL--` is the Phel input; `--PHP--` is the exact compiled PHP output.
- One fixture per behavior, named descriptively (`fn-variadic.test`, `try-one-catch.test`).
- Files live in `tests/php/Integration/Fixtures/<Category>/`; categories mirror constructs (`Def/`, `Fn/`, `Let/`, `Try/`, `Call/`).
- PHP output uses `\Phel::` helpers such as `addDefinition`, `map`, `keyword` and `vector`.
- Output embeds source locations, so regenerate it when changing the Phel input.

Never hand-write the `--PHP--` section. Leave it empty, run `./vendor/bin/phpunit --testsuite=integration --filter=<fixture-stem>`, copy the actual output from the failure diff, then check it is the output you intended. Broaden the filter only to diagnose a related failure.
