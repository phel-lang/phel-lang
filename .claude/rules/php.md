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

A module's `Factory` may **only `new` classes that live inside its own module or in `Phel\Shared`**. Concrete classes from `Phel\<OtherModule>\Application\…`, `…\Domain\…`, or `…\Infrastructure\…` must not be imported into a factory.

Where each kind of dependency lives:

| Kind | Home | How to consume |
|---|---|---|
| Pure stateless utility (no I/O, no module state) | `Phel\Shared` | `use Phel\Shared\Foo;` then `new Foo()` directly — e.g. `Munge`, `ColorStyle`, `ResourceUsageFormatter` |
| Cross-module contract interface (signatures only) | `Phel\Shared\…` or `Phel\<Other>\Domain\…Interface` | import as a type hint; obtain instance from Shared or via the owning facade |
| Stateful behaviour owned by a neighbour module (depends on config, registry, runtime state) | `Phel\<Other>\Facade` | inject the facade via `DependencyProvider` and call its public method, e.g. `$this->getOtherFacade()->doX(...)` |

If you find yourself wanting to add a `createX()` factory passthrough on a neighbour facade just so another module can `new` something, that's a signal the class is actually a Shared utility — move it there instead.

Quick smell test: if a factory has `use Phel\<OtherModule>\Application\…;` or instantiates such a class with `new`, it's wrong; either move the class to `Phel\Shared` (when it's a pure utility) or call the owning facade (when it's stateful behaviour).

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit with `--testsuite=unit,integration`
