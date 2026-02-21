---
description: PHP code style, quality rules, and module patterns
globs: src/php/**,tests/php/**
---

# PHP Conventions

## Code Style

- PSR-12 enforced by php-cs-fixer + rector
- Run `./vendor/bin/php-cs-fixer fix <file>` after creating or editing PHP files
- PHPStan level 5, Psalm level 1
- Prefer `final` classes unless inheritance is explicitly needed
- Use `readonly` properties where possible

## Module Pattern (Gacela)

- Each module exposes a `Facade` as its public API
- Use `DependencyProvider` for cross-module dependencies
- Never instantiate classes from other modules directly — use their Facade

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit with `--testsuite=unit,integration`
- Run `composer test-compiler` to execute PHP tests
