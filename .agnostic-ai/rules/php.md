---
description: PHP code style, quality rules, and module patterns
globs: src/php/**,tests/php/**
---

# PHP Conventions

## Code Style

- PER 3.0 enforced by php-cs-fixer + rector (auto-formats via PostToolUse hook — no manual run needed)
- PHPStan level 9 (max), Psalm level 1
- Prefer `final` classes unless inheritance is explicitly needed
- Use `readonly` properties where possible

## Type naming

- `@template` generic parameters are `T`-prefixed: `TKey`, `TValue`, not bare `K`/`V`. The prefix exists to keep a generic parameter distinguishable from a real class name at its use site.
- `@phpstan-type` aliases are **not** `T`-prefixed. They are file-scoped docblock macros, never ambiguous with a class, and several deliberately mirror external spec object names (the LSP handlers) where a prefix would break the correspondence.

## Module Pattern (Gacela)

- Each module exposes a `Facade` as its public API
- Use `Provider` for cross-module dependencies (`<Module>Provider` extending `AbstractProvider`; Gacela 2.0 removed `AbstractDependencyProvider` and resolves pillars by *filename* suffix, so the file must be `<Module>Provider.php`)
- Declare inherited pillar services explicitly with `#[ServiceMap]`: Facade `getFactory()` → module Factory; Factory and Provider `getConfig()` → module Config. Modules without a custom Config map to `AbstractConfig`.
- Register provider entries with `#[Provides(...)]`. Facade dependencies should be keyed by the Shared facade contract the consumer requests (`CompilerFacadeInterface::class`), not by provider-specific string constants. Keep string keys for non-facade services only.
- Never instantiate classes from other modules directly — use their Facade. Gacela `Facade`, `Factory`, `Config`, `Provider` classes are internal wiring, not user-facing API.

### Factory boundary rules

A module's `Factory` may **only `new` classes that live inside its own module or in `Phel\Shared`**. Concrete classes from `Phel\<OtherModule>\Application\…`, `…\Domain\…`, or `…\Infrastructure\…` must not be imported into a factory.

Where each kind of dependency lives:

| Kind | Home | How to consume |
|---|---|---|
| Pure stateless utility (no I/O, no module state) | `Phel\Shared` | `use Phel\Shared\Foo;` then `new Foo()` directly — e.g. `Munge`, `ColorStyle`, `ResourceUsageFormatter` |
| Cross-module contract interface (signatures only) | `Phel\Shared\…` or `Phel\<Other>\Domain\…Interface` | import as a type hint; obtain instance from Shared or via the owning facade |
| Stateful behaviour owned by a neighbour module (depends on config, registry, runtime state) | `Phel\<Other>\Facade` behind `Phel\Shared\Facade\<Other>FacadeInterface` when available | inject the facade contract via `Provider` and call its public method, e.g. `$this->getOtherFacade()->doX(...)` |

If you find yourself wanting to add a `createX()` factory passthrough on a neighbour facade just so another module can `new` something, that's a signal the class is actually a Shared utility — move it there instead.

Quick smell test: if a factory has `use Phel\<OtherModule>\Application\…;` or instantiates such a class with `new`, it's wrong; either move the class to `Phel\Shared` (when it's a pure utility) or call the owning facade (when it's stateful behaviour).

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit with `--testsuite=unit,integration`
