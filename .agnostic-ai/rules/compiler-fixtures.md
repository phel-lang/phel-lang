---
name: compiler-fixtures
description: 'Load when adding or changing a compiler `.test` fixture: sections, layout and how to produce the expected PHP.'
scope: tests/php/Integration/Fixtures
---

# Compiler Fixtures

A `.test` file holds `--PHEL--` (the input), an optional `--DEPRECATIONS--` section, then `--PHP--` (the exact compiled output):

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

- `--DEPRECATIONS--` lists, one per line, the notices the fixture raises on purpose. Any other notice fails it.
- One fixture per behavior, named for it (`fn-variadic.test`), under `Fixtures/<Category>/`, where categories mirror constructs (`Def/`, `Fn/`, `Let/`).
- Output embeds source locations, so regenerate it whenever the Phel input changes.
- `OrphanCallSlotAuditTest` fails when a `--PHP--` body reserves a `$__phel_call_N` or `$__phel_const_N` slot it never initializes.

Never hand-write `--PHP--`. Leave it empty, run `./vendor/bin/phpunit --testsuite=integration --filter=<fixture-stem>`, copy the actual output from the failure diff, then check it is the output you intended.
