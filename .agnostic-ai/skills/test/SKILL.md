---
description: Run the narrowest test scope for a change.
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
allowed-tools:
  - "shell(composer *)"
  - "shell(./vendor/bin/phpunit *)"
  - "shell(./bin/phel *)"
---

# Quick Test Runner

## Scope mapping

Use the narrowest scope for diagnosis or an explicit focused request. For the final gate, follow `.agnostic-ai/rules/build-test-and-development-commands.md`; a focused pass does not replace it.

| Changed                  | Command                                       | Notes                              |
|--------------------------|-----------------------------------------------|------------------------------------|
| `src/php/**`             | `composer test-compiler`                      | PHPUnit unit + integration         |
| `src/phel/**`            | `composer test-core`                          | Phel core tests                    |
| Single PHP module        | `./vendor/bin/phpunit tests/php/Unit/<Module>` | Fastest for focused work           |
| Integration fixtures     | `composer test-integration`                   | Paratest, integration suite only   |
| Single Phel file         | `./bin/phel test tests/phel/<file>`           | Target a specific test             |
| Any `.php` style change  | `composer test-quality`                       | Formatting, analysis and config    |
| Mixed PHP + Phel         | `composer test`                               | Run everything                     |

Every command is described in `.agnostic-ai/rules/build-test-and-development-commands.md`.

## Instructions

1. If the argument is empty or `all`:
   ```bash
   COMPOSER_PROCESS_TIMEOUT=0 composer test
   ```

2. If the argument is a known scope:
   - `quality` → `composer test-quality`
   - `compiler` → `composer test-compiler`
   - `core` → `composer test-core`
   - `quick` → `composer test-compiler && composer test-core` (skip static analysis)
   - `bench` → `composer phpbench`

3. If the argument looks like a test class or method name:
   ```bash
   ./vendor/bin/phpunit --filter "<argument>"
   ```

4. If the argument looks like a file path:
   ```bash
   ./vendor/bin/phpunit "<argument>"
   # or for Phel tests:
   ./bin/phel test "<argument>"
   ```

5. Report results clearly with pass/fail count.
