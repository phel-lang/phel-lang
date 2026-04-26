---
name: integration-fixture
description: Create or validate Phel compiler integration fixtures. Use when Codex needs a .test fixture under tests/php/Integration/Fixtures, expected emitted PHP, or guidance for --PHEL--/--PHP-- fixture format.
---

# Integration Fixture

## Workflow

1. Inspect available fixture categories:
   ```bash
   ls tests/php/Integration/Fixtures/
   ```

2. Parse the request as `<category> <name>`.
   - Category must normally match an existing directory under `tests/php/Integration/Fixtures/`.
   - If no category matches, list available categories and ask before creating a new one.
   - Use a descriptive file stem such as `fn-variadic` or `try-one-catch`.

3. Get the Phel input from the user or from the issue/task context. Keep one fixture focused on one behavior.

4. Generate expected PHP from the compiler. Do not hand-write the `--PHP--` section; it must match byte-for-byte, including source locations.

5. Write the fixture:
   ```text
   --PHEL--
   <phel source>
   --PHP--
   <exact compiled output>
   ```

6. Run the integration suite filtered to the relevant category:
   ```bash
   ./vendor/bin/phpunit --testsuite=integration --filter=<Category>
   ```

7. If the fixture fails, investigate the compiler behavior. Do not edit expected PHP merely to hide a regression.

## Constraints

- One fixture per behavior.
- Regenerate PHP output after every Phel input edit.
- Fixture PHP output uses `\Phel::` static helpers and should not add `use` statements.
