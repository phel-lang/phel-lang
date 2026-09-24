---
name: build-test-and-development-commands
description: Composer and CLI commands for building, testing and fixing.
---

# Commands

- `composer install`: required before any tooling.
- `composer test`: full gate (`test-quality`, `test-compiler`, `test-core`); mirrors CI. Prefix `COMPOSER_PROCESS_TIMEOUT=0`, or composer aborts it at 300s.
- `composer test-quality`: cs-fixer dry run, psalm, phpstan, rector, config validation.
- `composer test-compiler`: PHPUnit `unit` suite, then the `integration` suite through paratest. `composer test-unit` / `composer test-integration` run one of them.
- `composer test-core`: Phel tests via `bin/phel test`, in workers, then the `^:timing-sensitive` ones alone. `composer test-core:serial` runs them in one process.
- `composer fix`: rector, then cs-fixer.
- Focused runs: `./vendor/bin/phpunit --filter=<Class or method>`, `./bin/phel test tests/phel/<file>`.
- `./build/phar.sh`: builds `build/out/phel.phar`.

Run focused tests while working. Run `composer test` once, when the change is complete. The pre-commit hook (`tools/git-hooks/init.sh`) runs `composer test-all` when PHP or Phel files are staged.
