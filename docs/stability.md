# Stability Policy

This page is **normative**. It defines what a Phel version number promises, which
symbols that promise covers, and how something covered by it is allowed to change.

Until `1.0.0` ships, the policy below describes the *target*: it is what `1.x` will
guarantee, and the gates in CI already enforce the parts that can be enforced today.
`0.x` releases remain free to break, and the changelog marks every such change
**BREAKING**.

## Two promises

1. **Language stability.** Phel source that compiles on `1.0.0` compiles on every
   later `1.x`. Reader syntax, special forms and the public `phel.*` core API do not
   break inside the major. The frozen surface is listed in
   [the language surface spec](spec/language-surface.md).
2. **Embedding stability.** The PHP surface listed under
   [Public PHP API](#public-php-api) follows semver, so a project wiring Phel into
   its own tooling can take `1.x` updates without reading a diff.

Anything not covered by one of those two is explicitly not promised. That is not a
gap to be filled later; it is the boundary that makes the promise affordable.

## Public PHP API

A symbol is public if and only if it matches one of the rules below. Everything else
in `src/php/` is internal, carries an `@internal` annotation, and may change in any
release including a patch.

| # | Rule | Examples |
|---|------|----------|
| 1 | The `\Phel` runtime class and its `Phel\Phel` base | `Phel::vector()`, `Phel\Phel::bootstrap()` |
| 2 | `Phel\<Module>\<Module>Facade` | `Phel\Compiler\CompilerFacade` |
| 3 | `Phel\<Module>\<Module>FacadeInterface` | `Phel\Fiber\FiberFacadeInterface` |
| 4 | Everything under `Phel\Shared\` | `Phel\Shared\Facade\CompilerFacadeInterface`, `Phel\Shared\CompileOptions` |
| 5 | Everything under `Phel\Lang\` | `Phel\Lang\Symbol`, `Phel\Lang\Collections\Map\PersistentMapInterface` |
| 6 | Everything under `Phel\Config\` | `Phel\Config\PhelConfig`, `Phel\Config\ProjectLayout` |

Rule 1 exists because emitted PHP calls into it: every compiled `.phel` file is a
consumer, so `\Phel` is load-bearing for build artifacts produced by older versions.
Its `Phel\Phel` base is covered with it, because that is where `bootstrap()` and
`run()` live and an embedding project starts there.

Rules 4 to 6 are whole namespaces rather than hand-picked lists because they are the
namespaces a consumer cannot avoid: values that cross the facade boundary (`Lang`),
the contracts and transfers those facades speak in (`Shared`), and the object a
project's own `phel-config.php` constructs (`Config`).

### Internal by construction

Everything below is internal even when a public class happens to return it:

- `Phel\<Module>\Domain\`, `Phel\<Module>\Application\`, `Phel\<Module>\Infrastructure\`
- `*Factory`, `*Config`, `*DependencyProvider`, `*Provider` (the Gacela plumbing)
- `Phel\<Module>\Transfer\` (module-local transfers; the cross-module ones live in `Phel\Shared\Api\`)

Depending on an internal symbol is not forbidden, it is unsupported. Reaching for one
is a signal the facade is missing a method, and that is worth an issue.

### What counts as a break

For a public symbol, all of the following are breaking and may only land in a major:

- removing a class, interface, method, constant or public property
- narrowing a parameter type, adding a required parameter, or reordering parameters
- widening a return type, or changing it to an unrelated type
- adding a method to an interface, or making an existing method abstract
- changing a class from non-`final` to `final`, or removing a public constructor

The following are **not** breaks and may land in a minor or patch:

- adding a class, or adding a method to a `final` class
- adding an optional parameter at the end of a signature
- widening a parameter type or narrowing a return type
- any change to a symbol marked `@internal`

Interfaces under `Phel\Shared\Facade\` are the one place where "adding a method" bites
implementers rather than callers. The changelog labels those
**BREAKING (PHP API, implementers only)** so the two cases stay distinguishable.

### How it is enforced

`tests/php/Unit/Architecture/PublicApiSurfaceTest.php` reflects over every symbol
matched by the rules above and compares the result against the committed snapshot at
`tests/php/Unit/Architecture/public-api.snapshot.txt`. The rules themselves are code,
in `tests/php/Support/PublicApiSurface.php`, so this table and the gate cannot drift
apart. Any signature change to a public symbol fails the build until the snapshot is
regenerated:

```bash
composer api-surface:update
```

The regenerated diff is the backward-compatibility review. A change that shows up
there needs a changelog entry, and inside `1.x` it needs to be one of the non-breaking
shapes listed above.

The snapshot is deliberately a gate on the pull request that introduces the change,
not a comparison against the last release tag: a break is cheapest to discuss while
the diff that causes it is still open.

## Deprecation policy for 1.x

1. **Announce before removing.** A deprecated symbol ships with a notice for at least
   one full minor release before it can be removed. It is then removed only in a major.
2. **One channel.** Everything the compiler knows about, syntax and definitions alike,
   reports through `Phel\Compiler\Domain\Deprecation\DeprecationWarnings`, off by
   default and enabled with `--warn-deprecations` or `PHEL_WARN_DEPRECATIONS=1`. CLI
   option renames are the documented exception and always print on stderr, because a
   renamed flag is a single unmissable event rather than something scattered through
   source.
3. **No version promises in the message.** Deprecation notices never name a concrete
   removal version: the release such a message promises inevitably ships and the text
   goes stale. The tracking issue carries the schedule instead.
4. **A migration page, always.** Every live deprecation appears in
   [the deprecated surface map](migration/deprecated-surface.md) with its replacement
   and a mechanical before/after. When it is removed it moves to
   [removed](migration/removed-deprecated-core-fns.md), so the "still deprecated" page
   shrinks to exactly what is still shipped.
5. **PHP-side deprecations** use the native `#[\Deprecated]` attribute or a
   `@deprecated` annotation so `phpstan/phpstan-deprecation-rules` reports them
   downstream.

## PHP support policy

- `1.x` requires **PHP >= 8.4**. Raising that minimum is a breaking change and can only
  happen in a major.
- Every PHP minor from the minimum up to the newest stable release runs the full
  compiler and core suites in CI. A new PHP minor is added to the matrix within one
  Phel minor of its release.
- Support for a PHP minor is never dropped inside a major, including after that minor
  leaves the PHP project's own security-support window. Phel keeps testing it; the
  security posture of the runtime is the deploying project's call.

## Platform support

| Tier | Platforms | Meaning |
|---|---|---|
| Supported | Linux, macOS | Full compiler, core and PHAR suites run in CI on every push. A failure here blocks a release. |
| Best effort | Windows | A reduced suite runs in CI. Bugs are accepted and fixed, but a Windows-only failure does not block a release. |

The distinction is about what the project commits to, not about what works. Phel is
plain PHP and the parts that are platform-sensitive are narrow: path separators,
`readline` availability in the REPL, and the file-watching backends in
`phel watch`, which fall back to polling wherever the native backend is missing.

## Configuration surface

`phel-config.php` returns a `Phel\Config\PhelConfig`. Two things about it are frozen
for `1.x`:

- **The wire keys.** `PhelConfig::SRC_DIRS` is the string `'src-dirs'`, and every
  sibling constant is likewise the literal key Gacela reads. Renaming one silently
  changes the meaning of an existing project's config file, so the constants and their
  values are covered by rule 6 above.
- **The builder API.** `with*()` methods only ever gain new siblings; an existing one
  keeps its name, its parameter type and its "returns a new instance" contract.

The `.phel/` state directory layout is documented in
[project-layout.md](project-layout.md) and is likewise frozen: a tool may rely on
`.phel/cache/` and `.phel/repl-history` existing where they are. `PHEL_DIR` relocates
the whole tree.

## Explicitly not covered

None of the following is under semver, and none of it will be before `1.0`:

- The exact PHP source text the emitter produces. It is an implementation detail with
  a test suite pinning it, not an output format. Only its *behaviour* is promised.
- Compiler diagnostic wording, and the shape of error output.
- The `.phel/cache/` file format. It is keyed by source hash plus Phel version, so a
  version bump invalidates it by design.
- Anything under `tests/`, `tools/`, `build/` or `resources/`.
- The nREPL and LSP wire protocols beyond the upstream specifications they implement.

## See also

- [The language surface spec](spec/language-surface.md): the frozen half of promise 1
- [Clojure divergences](spec/clojure-divergences.md): where Phel deliberately differs
- [The currently deprecated surface](migration/deprecated-surface.md)
- [Architecture](internals/architecture.md): why the module boundary sits where it does
