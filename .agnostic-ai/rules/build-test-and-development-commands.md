---
name: build-test-and-development-commands
---

- `composer install`: sync PHP dependencies; required before running any tooling.
- `composer test`: full quality gate (static analysis, compiler tests, core tests); mirrors CI.
- `composer test-quality`: static analysis only (cs-fixer, psalm, phpstan, rector).
- `composer test-compiler`: PHPUnit suites (`unit`, `integration`).
- `composer test-core`: exercises Phel core tests via `bin/phel test`.
- `composer fix`: auto-fix code style (chains Rector and CS Fixer).
- `composer phpstan` / `composer psalm`: static analysis tuned for PHP 8.4.
- `composer phpbench`: run benchmarks; `composer phpbench-base` (baseline), `composer phpbench-ref` (compare).
- `./build/phar.sh`: creates `build/out/phel.phar` for distribution testing.
