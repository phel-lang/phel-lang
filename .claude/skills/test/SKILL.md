---
description: Run tests with smart filtering by scope, class, or file path
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
allowed-tools: "Bash(composer *), Bash(./vendor/bin/phpunit *), Bash(./bin/phel *)"
---

# Quick Test Runner

## Scope mapping

Run the **minimum** scope for the changed files. Prefer narrow over broad.

| Changed                  | Command                                       | Notes                              |
|--------------------------|-----------------------------------------------|------------------------------------|
| `src/php/**`             | `composer test-compiler`                      | PHPUnit unit + integration         |
| `src/phel/**`            | `composer test-core`                          | Phel core tests                    |
| `src/php/Compiler/**`    | `composer test-compiler`                      | Full compiler suite                |
| Single PHP module        | `./vendor/bin/phpunit --filter=ModuleName`    | Fastest for focused work           |
| Single Phel file         | `./bin/phel test tests/phel/<file>`           | Target a specific test             |
| Any `.php` style change  | `composer test-quality`                       | Static analysis only               |
| Mixed PHP + Phel         | `composer test`                               | Run everything                     |

Available `composer` scripts:

```bash
composer test              # All tests (quality + compiler + core)
composer test-quality      # Static analysis: cs-fixer, psalm, phpstan, rector
composer test-compiler     # PHPUnit unit + integration tests
composer test-core         # Phel core tests (./bin/phel test)
composer fix               # Auto-fix: rector + cs-fixer
```

## Instructions

1. If `$ARGUMENTS` is empty or `all`:
   ```bash
   composer test
   ```

2. If `$ARGUMENTS` is a known scope:
   - `quality` → `composer test-quality`
   - `compiler` → `composer test-compiler`
   - `core` → `composer test-core`
   - `quick` → `composer test-compiler && composer test-core` (skip static analysis)
   - `bench` → `composer phpbench`

3. If `$ARGUMENTS` looks like a test class or method name:
   ```bash
   ./vendor/bin/phpunit --filter "$ARGUMENTS"
   ```

4. If `$ARGUMENTS` looks like a file path:
   ```bash
   ./vendor/bin/phpunit "$ARGUMENTS"
   # or for Phel tests:
   ./bin/phel test "$ARGUMENTS"
   ```

5. Report results clearly with pass/fail count.
