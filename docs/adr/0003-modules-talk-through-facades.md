# ADR 0003: Modules talk to each other through facades only

- **Status**: Accepted (recorded retroactively; predates this record)
- **Date**: 2026-07-29

## Context

`src/php/` holds about twenty modules that are not peers: `Compiler` is central
and huge, `Lsp` and `Nrepl` are thin clients of it, `Lang` is a leaf under
everything. Without a boundary rule such a graph converges on everyone importing
everyone's internals, because the shortest path to a value is a direct `use`.

A class reached directly from another module cannot be renamed, cannot change its
constructor, and cannot move between `Domain/` and `Application/` without a
cross-module change. It is also what makes "internal" meaningless, which matters
because the project ships a public PHP API
([ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)).

## Decision

A module's public surface is its `Facade`. Cross-module access goes through it.

- Inject `*FacadeInterface`, never a concrete facade. Interfaces for the modules
  others depend on live in `Shared/Facade/`, so the dependency points at a shared
  kernel.
- `*Provider` classes name concrete facades. Gacela's locator resolves by class,
  and the provider is the only place allowed to.
- **A `Factory` may only `new` classes from its own module or `Phel\Shared`.**
  Cross-module instances arrive through the injected facade. A class a factory
  wants to construct is either a pure utility belonging in `Shared` or stateful
  behaviour belonging behind its owner's facade. Adding a `createX()` passthrough
  to a neighbour facade is the wrong answer and the signal the class is misplaced.
- `Lang`, `Shared` and `Config` are shared kernels every module may import: the
  values crossing facade boundaries, the contracts facades speak in, and the
  config object a project constructs.
- A class using `ServiceResolverAwareTrait` declares both the `#[ServiceMap]`
  attribute and the matching `@method` docblock. The attribute alone leaves the
  call `mixed`, and that spreads downstream.

## Consequences

Adding a method to your own facade before consuming someone else's internals is a
tax, paid on the pull request that needs the value. It is also when somebody asks
whether the boundary is in the right place.

One exception survives: `LspFactory::getLintFacade()` takes a concrete facade
because `Lint` has no interface yet. It is pinned at exactly one.

The rule is what lets everything under `Domain/`, `Application/` and
`Infrastructure/` be `@internal`, and what keeps a module `CLAUDE.md` down to an
API instead of a class list.

Cost: indirection that reads as ceremony for a one-method facade with one caller.
Accepted, because the alternative holds only where somebody remembered.

## Enforcement

- `phpstan.neon`: `Gacela\PHPStan\Rules\CrossModuleViaFacadeRule`, with
  `Phel\Shared`, `Phel\Lang` and `Phel\Config` declared as shared namespaces
- `FactoryModuleBoundaryTest`: the factory `new` rule
- `SatelliteFactoryFacadeInjectionTest`: fails on a second concrete-facade
  injection
- `IntraModuleLayeringTest`, `CompositionRootBoundaryTest`, `ProviderBindingIdTest`
- Gacela's `ignoreErrors` are discarded with `!` in `phpstan.neon`, so a class
  missing its `@method` annotations fails instead of resolving to `mixed`

## Alternatives considered

- **Convention enforced in review.** What existed before the architecture tests.
  It eroded where the shortcut was cheapest.
- **A namespace per layer instead of per module.** Makes the layer the unit of
  ownership; people work in modules.
- **Facade interfaces in the owning module.** Inverts nothing: the consumer still
  compiles against the owner.

## See also

- `src/php/CLAUDE.md`, [Architecture](../internals/architecture.md)
- [ADR 0004](0004-accept-four-module-cycles.md),
  [ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)
