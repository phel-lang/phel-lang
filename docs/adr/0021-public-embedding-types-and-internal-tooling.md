# ADR 0021: Expose embedding types and exclude internal tooling

- **Status**: Accepted
- **Date**: 2026-10-05
- **Supersedes**: [ADR 0005](0005-public-php-api-by-rule-and-snapshot.md)

## Context

Issue #3521 found public facade signatures that required constructing internal
classes, while the stability policy let those classes change in a patch. Whole
Shared and facade rules also exposed CLI plumbing as an embedding promise.

A host cannot compile or build from PHP without `EmitterResult`, `ReaderResult`
and `BuildOptions`. Moving every analyzer and tooling type would expand Shared
into the compiler and preserve the dependency cycle anyway (ADR 0004).

## Decision

The runtime `\Phel`, embedding facades and contracts, Lang, Config and Shared
remain public by namespace and facade rules, with explicit exceptions pinned by
`PublicApiSurface`. The three host-facing value objects live directly in Shared
and are public to construct and inspect.

An internal type named in a public signature is an opaque handle. Receiving it
and passing it back remain supported, including its type name and assignability.
Construction, member access and subclassing remain internal. This pins the
boundary without freezing the analyzer implementation.

The Lint, Mutate, Profile, Run, Fiber, Lsp, Nrepl, Api, Balance and Watch facades
are internal tooling, including the Shared Api and Run facade contracts.
Shared Performance classes, SupersededSourceMaps and LintRuleExplainerInterface
are internal too. Individual internal methods on public helpers are explicitly
excluded and annotated, as listed in the stability policy.

Default namespace rules still make new unexcluded Shared, Lang and Config
classes public. An exclusion must name the plumbing and carry `@internal`.
Generated-code entry points such as `fnSlot`, `Destructure`, generated symbols,
`ForeignFn` and `BuildFacade::unresolvedRequireError` remain public. Generated
artifacts still require the generating version under ADR 0020.

## Consequences

PHP hosts use the Shared names for the three value objects. The old internal
names are removed before 1.0. Hosts using Run or Api tooling directly lose the
previous target guarantee for those PHP signatures. Public `\Phel::run`,
Compiler, Build and Interop operations remain available for embedding; tooling
integrations must follow internal changes.

Dynamic binding reference counts and the profiler hook become private behind
typed accessors. The unused `anyActive` latch disappears. Their supported
operations are pinned, while direct writable storage is not an API.

The Compiler-to-Shared cycle remains accepted. Moving two result objects reduces
the Shared facade's Compiler imports from eleven to nine; it does not change
ADR 0004's decision to retain the cycle or weaken its tests.

## Enforcement

- `PublicApiSurfaceTest` pins the covered signatures and explicit exclusions.
- `InternalAnnotationTest` checks class annotations; exclusion tests check method annotations.
- `SharedCompilerBoundaryTest` pins nine Compiler imports and the public Shared value objects.
- Runtime tests cover binding visibility, frame cleanup and profiler hook lifecycle.
- `composer api-surface:update` regenerates the signature snapshot for review.

## Alternatives considered

- **Move all internal boundary types to Shared.** Freezes compiler structure without removing the cycle.
- **Annotations alone.** Cannot change the snapshot's namespace and facade membership rules.
- **Exclude any annotated public symbol automatically.** An accidental annotation silently removes a promise.
- **Keep all tooling public.** Commits 1.x to CLI implementation signatures that hosts need not construct.

## See also

[Stability policy](../stability.md) ·
[ADR 0004](0004-accept-four-module-cycles.md) ·
[ADR 0020](0020-build-artifacts-require-the-generating-version.md) ·
`.agnostic-ai/rules/module-shared.md`
