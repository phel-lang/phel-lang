---
name: build-test-and-development-commands
description: Composer and CLI commands for building, testing and fixing.
---

# Commands

- `composer install`: install dependencies before PHP or Phel tooling if `vendor/` is missing or the lock file changed.
- `composer test`: full gate (`test-quality`, `test-compiler`, `test-core`); mirrors CI. Prefix `COMPOSER_PROCESS_TIMEOUT=0`, or composer aborts it at 300s.
- `composer test-quality`: cs-fixer dry run, psalm, phpstan, rector, config validation.
- `composer test-compiler`: PHPUnit `unit` suite, then the `integration` suite through paratest. `composer test-unit` / `composer test-integration` run one of them.
- `composer test-core`: Phel tests via `bin/phel test`, in workers, then the `^:timing-sensitive` ones alone. `composer test-core:serial` runs them in one process.
- `composer fix`: rector, then cs-fixer.
- Focused runs: `./vendor/bin/phpunit --filter=<Class or method>`, `./bin/phel test tests/phel/<file>`.
- `./build/phar.sh`: builds `build/out/phel.phar`.

Use focused tests to reproduce a failure or verify a behavior while working. After reviewing the complete change, run `COMPOSER_PROCESS_TIMEOUT=0 composer test` once for PHP, Phel, integration fixtures, dependencies or runtime configuration changes. Run static analysis sequentially. Repeat checks only after changes or failures that affect their results.

For agent-spec-only changes, run `agnostic-ai validate`, `agnostic-ai lint --strict`, `agnostic-ai sync`, then `agnostic-ai sync --check`. Check rule activation with `agnostic-ai explain --file <path> --target <claude|codex>` when changing scope. Prose-only changes need a diff and link check. Shell tooling changes also need `composer test-tools`; downstream `resources/agents/` changes need `composer test-agents`.

The pre-commit hook (`tools/git-hooks/init.sh`) runs `composer test-all` when PHP or Phel files are staged. Let it supply the final gate when committing immediately with a staged PHP or Phel file that triggers it, using `COMPOSER_PROCESS_TIMEOUT=0`; otherwise run the applicable gate yourself, including for fixture-only changes. Do not claim checks you did not run.
