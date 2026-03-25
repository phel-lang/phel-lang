---
description: PHP code style, quality rules, and module patterns
globs: src/php/**,tests/php/**
---

# PHP Conventions

## Code Style

- PER 3.0 enforced by php-cs-fixer + rector (auto-formats via PostToolUse hook — no manual run needed)
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
