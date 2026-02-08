---
description: PHP code style and quality rules
globs: src/php/**,tests/php/**
---

# PHP Conventions

## Code Style

- PSR-12 enforced by php-cs-fixer + rector
- Run `./vendor/bin/php-cs-fixer fix <file>` after creating or editing PHP files
- PHPStan level 5, Psalm level 1

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit with `--testsuite=unit,integration`
- Run `composer test-compiler` to execute PHP tests
