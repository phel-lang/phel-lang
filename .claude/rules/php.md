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

### Factory boundary rules

A module's `Factory` may **only `new` classes that live inside its own module**. Concrete classes from `Phel\<OtherModule>\Application\…`, `…\Domain\…`, or `…\Infrastructure\…` must not be imported into a factory.

When the factory needs an instance owned by a neighbour module:

1. Add a `createX(): XInterface` (or equivalent) method to the neighbour's `Facade` and its `FacadeInterface` in `src/php/Shared/Facade/`.
2. Have the neighbour facade delegate to its own factory.
3. Consume it in the calling factory through `$this->getOtherFacade()->createX()`.

Interface types (e.g. `MungeInterface`, `GlobalEnvironmentInterface`) from `Phel\<OtherModule>\Domain\…` may be imported as type hints — these are part of the cross-module contract and are explicitly sanctioned by `src/php/Shared/CLAUDE.md`. The forbidden pattern is importing the **concrete** implementation class.

Quick smell test: if a factory has `use Phel\<OtherModule>\Application\…;` or instantiates such a class with `new`, it's wrong; route through the facade instead.

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit with `--testsuite=unit,integration`
