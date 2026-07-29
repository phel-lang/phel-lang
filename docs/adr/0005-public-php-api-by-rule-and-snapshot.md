# ADR 0005: Define the public PHP API by rule, gate it by snapshot

- **Status**: Accepted (recorded retroactively; landed in the run-up to 1.0)
- **Date**: 2026-07-29

## Context

Phel is embedded: a project wires the compiler into its own build, a framework
integration calls `RunFacade`, an editor plugin talks to `ApiFacade`. Promising
semver on the PHP side means first answering which of the roughly two thousand
classes under `src/php/` the promise covers.

The usual answer is an `@api` annotation per symbol. It fails in a predictable
way: the annotation is applied to what somebody remembered, a new class arrives
without one, and the boundary becomes whatever the last contributor assumed. The
inverse failure is worse. Without any marking, every class is effectively public,
because a consumer cannot tell, so the project cannot refactor anything and the
architecture rules from [ADR 0003](0003-modules-talk-through-facades.md) buy
nothing.

## Decision

Membership of the public PHP API is decided by six rules, and the rules are code.

| # | Rule |
|---|---|
| 1 | The `\Phel` runtime class |
| 2 | `Phel\<Module>\<Module>Facade` |
| 3 | `Phel\<Module>\<Module>FacadeInterface` |
| 4 | Everything under `Phel\Shared\` |
| 5 | Everything under `Phel\Lang\` |
| 6 | Everything under `Phel\Config\` |

Everything else is internal, carries `@internal`, and may change in a patch.

Rules 4 to 6 are whole namespaces rather than curated lists because they are the
namespaces a consumer cannot avoid: values that cross a facade boundary (`Lang`),
the contracts facades speak in (`Shared`), and the object a project's own
`phel-config.php` constructs (`Config`). Rule 1 exists because emitted PHP calls
into `\Phel`, so every compiled `.phel` file produced by an older version is a
consumer of it.

The rules live in `tests/php/Support/PublicApiSurface.php`.
`PublicApiSurfaceTest` reflects over every symbol they match and compares the
result to a committed snapshot at
`tests/php/Unit/Architecture/public-api.snapshot.txt`. Any signature change fails
the build until `composer api-surface:update` regenerates it, and the regenerated
diff **is** the backward-compatibility review.

## Consequences

The snapshot gates the pull request that introduces a change, not a comparison
against the last release tag. A break is cheapest to discuss while the diff
causing it is still open, and by release time the argument is over.

Because the rules are executable, [the stability policy](../stability.md) and the
gate cannot drift: the table on that page describes the same code the test runs.
`InternalAnnotationTest` pins the complement, so every class the rules reject
carries `@internal` and the split reaches an IDE and a static analyser instead of
living only in prose.

The whole-namespace rules are a real constraint. Everything added under
`Phel\Lang\` or `Phel\Shared\` is public on arrival, which means a helper dropped
there for convenience is now a promise. That pressure is intentional and it is the
main reason `Shared` stays a contracts-and-pure-utilities layer.

One gap is known and named rather than papered over: a public class inheriting
from a *vendor* base is rendered without that base's members, so a dependency
upgrade that changes an inherited signature is a real break the snapshot cannot
see. Phel ancestors are folded in and do not have this problem. Dependency
upgrades are where to look for it.

## Enforcement

- `tests/php/Unit/Architecture/PublicApiSurfaceTest.php` against
  `public-api.snapshot.txt`, rules in `tests/php/Support/PublicApiSurface.php`
- `tests/php/Unit/Architecture/InternalAnnotationTest.php` for the complement
- `composer api-surface:update` regenerates the snapshot
- The equivalent for the language side is
  `tests/php/Integration/Api/CoreApiSurfaceTest.php` against
  `core-api.snapshot.txt`

## Alternatives considered

- **`@api` annotations per symbol.** Rejected: opt-in marking degrades to whatever
  was remembered, and a missing annotation fails silently in the dangerous
  direction.
- **Comparing against the previous release tag.** Rejected: it moves the
  conversation to release day, when the cost of reverting is highest.
- **A hand-written list of public classes in a Markdown page.** Rejected: prose
  cannot fail a build, and this project's documentation rule is that a promise
  nobody can fail is a wish.

## See also

- [Stability policy](../stability.md): the normative statement of these rules
- [Language surface spec](../spec/language-surface.md): the same treatment for
  Phel-level definitions
- [ADR 0003](0003-modules-talk-through-facades.md)
