# ADR 0005: Define the public PHP API by rule, gate it by snapshot

- **Status**: Accepted (recorded retroactively; landed in the run-up to 1.0)
- **Date**: 2026-07-29

## Context

Phel is embedded: projects wire the compiler into their build, integrations call
`RunFacade`, editor plugins call `ApiFacade`. Semver on the PHP side means first
saying which of the ~1080 classes under `src/php/` it covers.

Per-symbol `@api` annotations degrade to what somebody remembered, and a missing
one fails silently in the dangerous direction. No marking is worse: everything is
effectively public, nothing can be refactored, and [ADR 0003](0003-modules-talk-through-facades.md)
buys nothing.

## Decision

Six rules decide membership, and the rules are code.

| # | Rule |
|---|---|
| 1 | The `\Phel` runtime class |
| 2 | `Phel\<Module>\<Module>Facade` |
| 3 | `Phel\<Module>\<Module>FacadeInterface` |
| 4 | Everything under `Phel\Shared\` |
| 5 | Everything under `Phel\Lang\` |
| 6 | Everything under `Phel\Config\` |

Everything else is internal, carries `@internal`, may change in a patch.

Rules 4 to 6 are whole namespaces because a consumer cannot avoid them: values
crossing a facade boundary (`Lang`), the contracts facades speak in (`Shared`), the
object `phel-config.php` constructs (`Config`). Rule 1 exists because emitted PHP
calls `\Phel`, so every compiled file from an older version is a consumer.

Rules live in `tests/php/Support/PublicApiSurface.php`. `PublicApiSurfaceTest`
reflects over every matched symbol and compares against
`tests/php/Unit/Architecture/public-api.snapshot.txt`. A signature change fails the
build until `composer api-surface:update` regenerates it; that diff is the
backward-compatibility review.

## Consequences

The gate runs on the PR introducing the change, not against the last release tag. A
break is cheapest to discuss while its diff is open.

Executable rules mean [the stability policy](../stability.md) and the gate cannot
drift. `InternalAnnotationTest` pins the complement, so the split reaches IDEs and
static analysis instead of prose.

Whole-namespace rules are a real constraint: anything added under `Phel\Lang\` or
`Phel\Shared\` is public on arrival. That pressure keeps `Shared` a
contracts-and-utilities layer.

Known gap: a public class inheriting a *vendor* base renders without that base's
members, so a dependency upgrade changing an inherited signature is a break the
snapshot cannot see. Phel ancestors are folded in.

## Enforcement

- `PublicApiSurfaceTest` against `public-api.snapshot.txt`; rules in
  `tests/php/Support/PublicApiSurface.php`
- `InternalAnnotationTest` for the complement
- `composer api-surface:update` regenerates
- Language-side equivalent: `CoreApiSurfaceTest` against `core-api.snapshot.txt`

## Alternatives considered

- **`@api` per symbol.** Opt-in marking degrades to what was remembered.
- **Compare against the previous release tag.** Moves the argument to release day.
- **A hand-written list in Markdown.** Prose cannot fail a build.

## See also

[Stability policy](../stability.md) ·
[Language surface spec](../spec/language-surface.md) ·
[ADR 0003](0003-modules-talk-through-facades.md)
