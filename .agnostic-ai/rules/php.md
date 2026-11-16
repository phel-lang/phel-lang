---
name: php
description: PHP code style, typing, module boundaries and PHPUnit conventions
globs:
  - src/php/**
  - tests/php/**
---

# PHP Conventions

## Code Style

- PER 3.0, enforced by php-cs-fixer and Rector. PHPStan level 9 (max), Psalm level 1.
- The post-edit hook runs cs-fixer on each edited PHP file and deletes an unused `use`: add an import in the same edit as its first usage.
- `final` classes unless inheritance is needed; `readonly` properties where possible.
- `@template` parameters are `T`-prefixed (`TKey`, `TValue`) so they never read as a class name. `@phpstan-type` aliases are not: they are file-scoped, and some mirror external spec names (the LSP handlers).
- Before adding a Facade, Factory, Provider or cross-module call, read `.agnostic-ai/rules/module-map.md`.

## Testing

- Test methods are snake_case: `test_it_does_something()`.
- Use attributes (`#[DataProvider('name')]`, `#[Test]`). PHPUnit 12 ignores docblock annotations, so a `@dataProvider` test runs without data.
- Suites: `unit`, `integration`.
