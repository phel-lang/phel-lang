---
description: PHP code style, typing, module boundaries and PHPUnit conventions
globs: src/php/**,tests/php/**
---

# PHP Conventions

## Code Style

- PER 3.0, enforced by php-cs-fixer and rector. The post-edit hook runs cs-fixer on every edited PHP file.
- The hook deletes an unused `use`. Add an import in the same edit as its first usage.
- PHPStan level 9 (max), Psalm level 1
- Prefer `final` classes unless inheritance is explicitly needed
- Use `readonly` properties where possible

## Type naming

- `@template` generic parameters are `T`-prefixed: `TKey`, `TValue`, not bare `K`/`V`. The prefix exists to keep a generic parameter distinguishable from a real class name at its use site.
- `@phpstan-type` aliases are **not** `T`-prefixed. They are file-scoped docblock macros, never ambiguous with a class, and several deliberately mirror external spec object names (the LSP handlers) where a prefix would break the correspondence.

## Modules (Gacela)

Read `src/php/CLAUDE.md` before adding a Facade, Factory, Provider or a cross-module call. It owns the wiring rules (`#[ServiceMap]`, `#[Provides]` keyed by the Shared facade contract) and the factory boundary: a Factory only `new`s classes from its own module or `Phel\Shared`, and other modules are reached through their facade.

## Testing

- Test method names use snake_case: `test_it_does_something()`.
- Use attributes (`#[DataProvider('name')]`, `#[Test]`). PHPUnit 12 ignores docblock annotations, so a `@dataProvider` test runs without data.
- Suites: `unit`, `integration`.
