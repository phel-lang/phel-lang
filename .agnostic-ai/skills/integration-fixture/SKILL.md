---
description: Add a .test integration fixture for one behavior.
argument-hint: "[category] [name]"
disable-model-invocation: true
allowed-tools:
  - "read"
  - "write"
  - "edit"
  - "Glob"
  - "shell(ls *)"
  - "shell(./vendor/bin/phpunit *)"
  - "shell(composer *)"
---

# Integration Fixture

Scaffolds a new `.test` fixture in the two-section `--PHEL--` / `--PHP--` format defined in `.agnostic-ai/rules/compiler-fixtures.md`.

## Context

Run first: `ls tests/php/Integration/Fixtures/`.

## Instructions

1. **Parse the argument** as `<category> <name>`.
   - Category must match an existing dir under `tests/php/Integration/Fixtures/` (e.g. `Def`, `Fn`, `Let`, `Try`, `Call`, `If`, `Apply`, `Foreach`, `Keyword`, `Do`, `Inline`).
   - If no match, inspect sibling fixtures and choose the category that owns the behavior. Create a category only when no existing one fits.
   - `name` is the file stem (kebab or descriptive): `fn-variadic`, `try-one-catch`.

2. **Use the task's Phel input** (one form or a small block), or derive the smallest reproducer from the described behavior. Ask only when the intended behavior is missing.

3. **Write the fixture** as `tests/php/Integration/Fixtures/<Category>/<name>.test` with the Phel input and an empty `--PHP--` section:
   ```
   --PHEL--
   <phel source>
   --PHP--
   ```

4. **Capture the output** as `.agnostic-ai/rules/compiler-fixtures.md` describes, and check it is the output you intended.

5. **Diagnose a mismatch** with a category-filtered run only when needed to capture output or investigate a failure:
   ```bash
   ./vendor/bin/phpunit --testsuite=integration --filter=<Category>
   ```

6. If the fixture fails, trace the mismatch against the intended behavior. Regenerate expected PHP only for an intentional behavior or source-location change; otherwise fix the compiler within the assigned scope or report the defect. After reviewing the completed change, select verification per `.agnostic-ai/rules/build-test-and-development-commands.md`, including for fixture-only changes. Reuse passing fixture checks on unchanged code.

## Constraints

- One fixture per behavior. Any edit to the Phel input means regenerating the PHP section: it embeds source locations.
