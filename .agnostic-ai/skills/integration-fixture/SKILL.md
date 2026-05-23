---
description: Create or validate a `.test` integration fixture under tests/php/Integration/Fixtures
argument-hint: "[category] [name]"
disable-model-invocation: true
allowed-tools: "Read, Write, Edit, Glob, Bash(ls *), Bash(./vendor/bin/phpunit *)"
---

# Integration Fixture

Scaffolds a new `.test` fixture in the two-section `--PHEL--` / `--PHP--` format defined in `.claude/rules/integration-tests.md`.

## Context

!`ls tests/php/Integration/Fixtures/`

## Instructions

1. **Parse `$ARGUMENTS`** as `<category> <name>`.
   - Category must match an existing dir under `tests/php/Integration/Fixtures/` (e.g. `Def`, `Fn`, `Let`, `Try`, `Call`, `If`, `Apply`, `Foreach`, `Keyword`, `Do`, `Inline`).
   - If no match, list available categories and ask before creating a new one.
   - `name` is the file stem (kebab or descriptive): `fn-variadic`, `try-one-catch`.

2. **Ask the user for the Phel input** (one form or a small block).

3. **Compile it locally to capture the expected PHP output**:
   ```bash
   ./vendor/bin/phpunit --testsuite=integration --filter=<related>
   ```
   Or use the compiler Facade directly via a throwaway script if needed — do NOT hand-write PHP output; it must match byte-for-byte including source locations.

4. **Write the fixture** as `tests/php/Integration/Fixtures/<Category>/<name>.test`:
   ```
   --PHEL--
   <phel source>
   --PHP--
   <exact compiled output>
   ```

5. **Run the integration suite filtered to the new file** to confirm it passes:
   ```bash
   ./vendor/bin/phpunit --testsuite=integration --filter=<Category>
   ```

6. If the fixture fails, do NOT edit the expected PHP to match. Instead, report the diff — a failing fixture usually means a compiler regression or the input is not idiomatic.

## Constraints

- One fixture per behavior — never bundle unrelated cases.
- Source locations in the PHP output embed line/column metadata — any edit to the Phel input means regenerating the PHP section.
- PHP output uses `\Phel::` static helpers (`addDefinition`, `map`, `keyword`, `vector`, etc.). Never `use` statements inside fixtures.
