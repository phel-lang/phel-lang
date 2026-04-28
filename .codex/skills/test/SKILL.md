---
name: test
description: Run Phel tests with smart scope selection. Use when Codex is asked to run all tests, quality checks, compiler tests, core tests, phpunit filters, Phel test files, or benchmark-as-test checks.
---

# Test Runner

## Workflow

Choose the narrowest command that proves the change, then broaden before finalizing.

| Request | Command |
|---------|---------|
| empty or `all` | `composer test` |
| `quality` | `composer test-quality` |
| `compiler` | `composer test-compiler` |
| `core` | `composer test-core` |
| `quick` | `composer test-compiler && composer test-core` |
| `bench` | `composer phpbench` |
| PHPUnit class or method | `./vendor/bin/phpunit --filter "<filter>"` |
| PHP test file path | `./vendor/bin/phpunit "<path>"` |
| Phel test file path | `./bin/phel test "<path>"` |

Report the exact command, pass/fail status, and the relevant failure summary when tests fail.
