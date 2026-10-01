---
description: Run the narrowest test scope for a change.
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Bash(composer *), Bash(./vendor/bin/phpunit *), Bash(./bin/phel *)"
x-codex:
  interface:
    display_name: Test
---

# Quick Test Runner

## Scope mapping

Run the **minimum** scope for the changed files. Prefer narrow over broad.

| Changed                  | Command                                       | Notes                              |
|--------------------------|-----------------------------------------------|------------------------------------|
| `src/php/**`             | `composer test-compiler`                      | PHPUnit unit + integration         |
| `src/phel/**`            | `composer test-core`                          | Phel core tests                    |
| Single PHP module        | `./vendor/bin/phpunit tests/php/Unit/<Module>` | Fastest for focused work           |
| Integration fixtures     | `composer test-integration`                   | Paratest, integration suite only   |
| Single Phel file         | `./bin/phel test tests/phel/<file>`           | Target a specific test             |
| Any `.php` style change  | `composer test-quality`                       | Static analysis only               |
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
