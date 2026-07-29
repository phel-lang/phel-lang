# ADR 0003: Modules talk to each other through facades only

- **Status**: Accepted (recorded retroactively; the decision predates this record)
- **Date**: 2026-07-29

## Context

`src/php/` holds around twenty modules that are not peers: `Compiler` is enormous
and central, `Lsp` and `Nrepl` are thin clients of it, `Lang` is a leaf everything
depends on. Without a boundary rule, a codebase shaped like that converges on
every module importing every other module's internals, because the shortest path
to a value is always a direct `use`.

The specific failure that rule prevents is not aesthetic. A class reached directly
from another module cannot be renamed, cannot change its constructor, and cannot be
moved between `Domain/` and `Application/` without a cross-module change. In a
project that ships a public PHP API (see
[ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)), the boundary is also what
makes "internal" mean something.

## Decision

A module's public surface is its `Facade`. Cross-module access goes through it and
through nothing else.

- Consumers inject the `*FacadeInterface`, never a concrete facade. Interfaces for
  the modules other modules depend on live in `Shared/Facade/` so the dependency
  points at a shared kernel rather than at the owning module.
- `*Provider` classes are the exception that makes the rule work: they name
  concrete facades, because Gacela's locator resolves by class, and they are the
  only place allowed to.
- **A `Factory` may only `new` classes from its own module or from `Phel\Shared`.**
  A cross-module instance arrives through the injected facade. When a factory wants
  to construct a neighbour's class, that class is either a pure utility that
  belongs in `Shared` or stateful behaviour that belongs behind the neighbour's
  facade. Adding a `createX()` passthrough to a neighbour facade is the wrong
  answer and the signal that the class is misplaced.
- `Lang`, `Shared` and `Config` are shared kernels: every module may import them
  directly. They are the values that cross facade boundaries, the contracts those
  facades speak in, and the config object a project constructs.
- A class using `ServiceResolverAwareTrait` declares both the `#[ServiceMap]`
  attribute (runtime resolution) and the matching `@method` docblock (static
  analysis). The attribute alone leaves the call `mixed`, and that `mixed` spreads
  through everything downstream of it.

## Consequences

Adding a method to your own facade before consuming someone else's internals is a
real tax, paid on the pull request that needs the value. It is also the moment
somebody asks whether the module boundary is in the right place, which is the
point.

One exception survives: `LspFactory::getLintFacade()` takes a concrete facade,
because `Lint` has no interface yet. It is pinned, so it stays exactly one.

The rule is what lets `@internal` be applied to everything under `Domain/`,
`Application/` and `Infrastructure/` with a straight face, and it is the reason a
module's `CLAUDE.md` can document a small API instead of a class list.

The cost is indirection that occasionally reads as ceremony, particularly for a
facade with one method used by one caller. That case is real and is accepted:
the alternative is a boundary that holds only where somebody remembered.

## Enforcement

- `phpstan.neon` runs `Gacela\PHPStan\Rules\CrossModuleViaFacadeRule` with
  `Phel\Shared`, `Phel\Lang` and `Phel\Config` declared as shared namespaces, so a
  cross-module reach fails static analysis rather than review.
- `tests/php/Unit/Architecture/FactoryModuleBoundaryTest.php`: the factory `new`
  rule.
- `tests/php/Unit/Architecture/SatelliteFactoryFacadeInjectionTest.php`: fails on a
  second concrete-facade injection.
- `tests/php/Unit/Architecture/IntraModuleLayeringTest.php`,
  `CompositionRootBoundaryTest.php`, `ProviderBindingIdTest.php` pin the rest of
  the wiring.
- PHPStan's Gacela `ignoreErrors` are discarded with `!` in `phpstan.neon`, so a
  class that forgets its `@method` annotations fails instead of resolving to
  `mixed`.

## Alternatives considered

- **Convention only, enforced in review.** This is what existed before the
  architecture tests, and it eroded exactly where the shortcut was cheapest.
- **One namespace per layer instead of per module.** Rejected: it makes the layer
  the unit of ownership, and the unit people actually work in is the module.
- **Public interfaces in the owning module rather than `Shared/`.** Rejected for
  the modules everything depends on: it inverts nothing, since the consumer still
  compiles against the owner.

## See also

- `src/php/CLAUDE.md`: the normative version of these rules
- [Architecture](../internals/architecture.md)
- [ADR 0004](0004-accept-four-module-cycles.md): where the graph is deliberately
  not acyclic
- [ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)
