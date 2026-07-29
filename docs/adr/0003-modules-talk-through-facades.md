# ADR 0003: Modules talk to each other through facades only

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

`src/php/` holds ~20 modules that are not peers: `Compiler` is central and huge,
`Lsp` and `Nrepl` are thin clients, `Lang` is a leaf under everything. Without a
boundary rule such a graph converges on everyone importing everyone's internals,
because a direct `use` is always the shortest path.

A class reached directly from another module cannot be renamed, cannot change its
constructor, cannot move between `Domain/` and `Application/`. It also makes
"internal" meaningless, which matters because the project ships a public PHP API
([ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)).

## Decision

A module's public surface is its `Facade`.

- Inject `*FacadeInterface`, never a concrete facade. Interfaces for modules others
  depend on live in `Shared/Facade/`.
- `*Provider` classes name concrete facades: Gacela's locator resolves by class and
  the provider is the only place allowed to.
- **A `Factory` may only `new` classes from its own module or `Phel\Shared`.**
  Cross-module instances come through the injected facade. A class a factory wants
  to construct is either a pure utility belonging in `Shared` or stateful behaviour
  belonging behind its owner's facade; a `createX()` passthrough on a neighbour
  facade is the signal it is misplaced.
- `Lang`, `Shared`, `Config` are shared kernels every module may import: values
  crossing facade boundaries, the contracts facades speak in, the config object a
  project constructs.
- A class using `ServiceResolverAwareTrait` declares both the `#[ServiceMap]`
  attribute and the matching `@method` docblock. The attribute alone leaves the
  call `mixed`, which spreads downstream.

## Consequences

- Adding a method to your own facade before consuming someone else's internals is a
  tax, paid on the PR that needs the value, and the moment somebody asks whether the
  boundary sits right.
- One exception survives: `LspFactory::getLintFacade()` takes a concrete facade
  because `Lint` has no interface yet. Pinned at exactly one.
- The rule is what lets everything under `Domain/`, `Application/` and
  `Infrastructure/` be `@internal`, and keeps a module `CLAUDE.md` to an API rather
  than a class list.
- Cost: indirection that reads as ceremony for a one-method facade with one caller.
  The alternative holds only where somebody remembered.

## Enforcement

- `phpstan.neon`: `Gacela\PHPStan\Rules\CrossModuleViaFacadeRule`, with
  `Phel\Shared`, `Phel\Lang`, `Phel\Config` as shared namespaces
- `FactoryModuleBoundaryTest`: the factory `new` rule
- `SatelliteFactoryFacadeInjectionTest`: fails on a second concrete-facade injection
- `IntraModuleLayeringTest`, `CompositionRootBoundaryTest`, `ProviderBindingIdTest`
- Gacela's `ignoreErrors` are discarded with `!`, so a class missing its `@method`
  annotations fails instead of resolving to `mixed`

## Alternatives considered

- **Convention enforced in review.** The pre-test status quo; eroded where the
  shortcut was cheapest.
- **A namespace per layer.** Makes the layer the unit of ownership; people work in
  modules.
- **Facade interfaces in the owning module.** Inverts nothing: the consumer still
  compiles against the owner.

## See also

`src/php/CLAUDE.md` · [Architecture](../internals/architecture.md) ·
[ADR 0004](0004-accept-four-module-cycles.md) ·
[ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)
