---
description: Add a .test integration fixture for one behavior.
argument-hint: "[category] [name]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Write, Edit, Glob, Bash(ls *), Bash(./vendor/bin/phpunit *)"
---

# Integration Fixture

Scaffolds a new `.test` fixture in the two-section `--PHEL--` / `--PHP--` format defined in `.agnostic-ai/rules/integration-tests.md`.

## Context

!`ls tests/php/Integration/Fixtures/`

## Instructions

1. **Parse `$ARGUMENTS`** as `<category> <name>`.
   - Category must match an existing dir under `tests/php/Integration/Fixtures/` (e.g. `Def`, `Fn`, `Let`, `Try`, `Call`, `If`, `Apply`, `Foreach`, `Keyword`, `Do`, `Inline`).
   - If no match, list available categories and ask before creating a new one.
   - `name` is the file stem (kebab or descriptive): `fn-variadic`, `try-one-catch`.

2. **Ask the user for the Phel input** (one form or a small block).

3. **Write the fixture** as `tests/php/Integration/Fixtures/<Category>/<name>.test` with the Phel input and an empty `--PHP--` section:
   ```
   --PHEL--
   <phel source>
   --PHP--
   ```

4. **Capture the output** as `.agnostic-ai/rules/integration-tests.md` describes, and check it is the output you intended.

5. **Run the integration suite filtered to the new file** to confirm it passes:
   ```bash
   ./vendor/bin/phpunit --testsuite=integration --filter=<Category>
   ```

6. If the fixture fails, do NOT edit the expected PHP to match. Instead, report the diff: a failing fixture usually means a compiler regression or the input is not idiomatic.

## Constraints

- One fixture per behavior. Any edit to the Phel input means regenerating the PHP section: it embeds source locations.
