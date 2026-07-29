# ADR 0005: Define the public PHP API by rule, gate it by snapshot

- **Status**: Accepted (recorded retroactively; landed in the run-up to 1.0)
- **Date**: 2026-07-29

## Context

Phel is embedded: projects wire the compiler into their own build, framework
integrations call `RunFacade`, editor plugins call `ApiFacade`. Promising semver
on the PHP side means first saying which of the ~2000 classes under `src/php/` the
promise covers.

Per-symbol `@api` annotations fail predictably: applied to what somebody
remembered, missing on new classes, and the boundary becomes the last
contributor's assumption. No marking at all is worse: everything is effectively
public, so nothing can be refactored and the rules from
[ADR 0003](0003-modules-talk-through-facades.md) buy nothing.

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

Everything else is internal, carries `@internal`, and may change in a patch.

Rules 4 to 6 are whole namespaces because they are what a consumer cannot avoid:
values crossing a facade boundary (`Lang`), the contracts facades speak in
(`Shared`), and the object a project's `phel-config.php` constructs (`Config`).
Rule 1 exists because emitted PHP calls `\Phel`, so every compiled file from an
older version is a consumer.

Rules live in `tests/php/Support/PublicApiSurface.php`. `PublicApiSurfaceTest`
reflects over every matched symbol and compares against
`tests/php/Unit/Architecture/public-api.snapshot.txt`. A signature change fails
the build until `composer api-surface:update` regenerates it, and that diff is the
backward-compatibility review.

## Consequences

The gate runs on the pull request introducing the change, not against the last
release tag. A break is cheapest to discuss while its diff is open.

Because the rules are executable, [the stability policy](../stability.md) and the
gate cannot drift. `InternalAnnotationTest` pins the complement, so the split
reaches IDEs and static analysis instead of living in prose.

Whole-namespace rules are a real constraint: anything added under `Phel\Lang\` or
`Phel\Shared\` is public on arrival. That pressure is intentional and keeps
`Shared` a contracts-and-utilities layer.

Known gap: a public class inheriting from a *vendor* base renders without that
base's members, so a dependency upgrade changing an inherited signature is a break
the snapshot cannot see. Phel ancestors are folded in.

## Enforcement

- `PublicApiSurfaceTest` against `public-api.snapshot.txt`, rules in
  `tests/php/Support/PublicApiSurface.php`
- `InternalAnnotationTest` for the complement
- `composer api-surface:update` regenerates
- Language-side equivalent: `CoreApiSurfaceTest` against `core-api.snapshot.txt`

## Alternatives considered

- **`@api` per symbol.** Opt-in marking degrades to what was remembered, and a
  missing annotation fails silently in the dangerous direction.
- **Compare against the previous release tag.** Moves the conversation to release
  day, when reverting costs most.
- **A hand-written list in Markdown.** Prose cannot fail a build.

## See also

- [Stability policy](../stability.md),
  [Language surface spec](../spec/language-surface.md)
- [ADR 0003](0003-modules-talk-through-facades.md)
