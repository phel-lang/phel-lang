# Stability Policy

**Normative.** What a Phel version number promises, which symbols it covers, and
how those are allowed to change.

Until `1.0.0` ships this describes the *target*: what `1.x` will guarantee, with
the enforceable parts already gated in CI. `0.x` remains free to break, and the
changelog marks every such change **BREAKING**.

## Two promises

1. **Language stability.** Phel source that compiles on `1.0.0` compiles on every
   later `1.x`. Reader syntax, special forms and the public `phel.*` core API do
   not break inside the major. Frozen surface:
   [the language surface spec](spec/language-surface.md).
2. **Embedding stability.** The PHP surface under [Public PHP API](#public-php-api)
   follows semver, so a project wiring Phel into its own tooling can take `1.x`
   updates without reading a diff.

Anything else is explicitly not promised. That is the boundary that makes the
promise affordable, not a gap to fill later.

## Public PHP API

A symbol is public if and only if it matches a rule below. Everything else in
`src/php/` is internal, carries `@internal`, and may change in any release
including a patch.

| # | Rule | Examples |
|---|------|----------|
| 1 | The `\Phel` runtime class | `Phel::vector()`, `Phel::bootstrap()` |
| 2 | `Phel\<Module>\<Module>Facade` | `Phel\Compiler\CompilerFacade` |
| 3 | `Phel\<Module>\<Module>FacadeInterface` | `Phel\Fiber\FiberFacadeInterface` |
| 4 | Everything under `Phel\Shared\` | `Phel\Shared\Facade\CompilerFacadeInterface`, `Phel\Shared\CompileOptions` |
| 5 | Everything under `Phel\Lang\` | `Phel\Lang\Symbol`, `Phel\Lang\Collections\Map\PersistentMapInterface` |
| 6 | Everything under `Phel\Config\` | `Phel\Config\PhelConfig`, `Phel\Config\ProjectLayout` |

Rule 1 exists because emitted PHP calls into it: every compiled `.phel` file is a
consumer, so `\Phel` is load-bearing for build artifacts from older versions. Its
`Phel\Phel` base stays internal, but the members it declares (`bootstrap()`,
`run()`, `configFn()` among them) are reachable through the child and covered as
part of `\Phel`.

Rules 4 to 6 are whole namespaces rather than curated lists because they are what
a consumer cannot avoid: values crossing the facade boundary (`Lang`), the
contracts those facades speak in (`Shared`), and the object a project's
`phel-config.php` constructs (`Config`).

Why the rules take this shape:
[ADR 0005](adr/0005-public-php-api-by-rule-and-snapshot.md).

### Internal by construction

Internal even when a public class returns it:

- `Phel\<Module>\Domain\`, `…\Application\`, `…\Infrastructure\`
- `*Factory`, `*Config`, `*Provider` (the Gacela plumbing)
- `Phel\<Module>\Transfer\` (cross-module transfers live in `Phel\Shared\Api\`)

Depending on an internal symbol is not forbidden, it is unsupported. Reaching for
one signals a missing facade method, which is worth an issue.

### What counts as a break

Breaking for a public symbol, major only:

- removing a class, interface, method, constant or public property
- narrowing a parameter type, adding a required parameter, reordering parameters
- widening a return type, or changing it to an unrelated type
- adding a method to an interface, or making an existing method abstract
- changing a class from non-`final` to `final`, or removing a public constructor

Not breaking, fine in a minor or patch:

- adding a class, or a method to a `final` class
- adding an optional parameter at the end of a signature
- widening a parameter type, narrowing a return type
- any change to an `@internal` symbol

Interfaces under `Phel\Shared\Facade\` are the one place where adding a method
bites implementers rather than callers. The changelog labels those
**BREAKING (PHP API, implementers only)**.

### How it is enforced

`tests/php/Unit/Architecture/PublicApiSurfaceTest.php` reflects over every symbol
the rules match and compares against
`tests/php/Unit/Architecture/public-api.snapshot.txt`. The rules themselves are
code, in `tests/php/Support/PublicApiSurface.php`, so this table and the gate
cannot drift apart. Any signature change fails the build until:

```bash
composer api-surface:update
```

That regenerated diff is the backward-compatibility review. The gate is on the
pull request introducing the change, not a comparison against the last release
tag: a break is cheapest to discuss while its diff is open.

`InternalAnnotationTest` pins the complement, so the split reaches an IDE and a
static analyser rather than living only here.

One gap: a public class inheriting a *vendor* base is rendered without that
base's members, so a dependency upgrade changing an inherited signature is a real
break the snapshot cannot see. Phel ancestors are folded in. Dependency upgrades
are where to look.

## What a deployment loads

`phel build` emits PHP, and the emitted PHP is what production runs. Measured on
a stock `phel init` project, `require`-ing the built entry point declares classes
from four places and no others:

| Namespace | Classes | Why |
|---|---|---|
| `Phel\Lang\` | ~690 | the runtime: persistent collections, `AbstractFn`, `Registry` |
| `Phel\Compiler\` | 7 | `GlobalEnvironmentSingleton` and the environment it resolves through. The emitter bakes that FQN into every compiled file, so it is an ABI shim rather than the compiler running |
| `Phel\Shared\` | 3 | `Munge` and the printer reached from generated code |
| `Phel\Phel` | 1 | `addDefinition()` / `getDefinition()`, which every compiled file calls |

Nothing from `Run`, `Console`, `Api`, `Lsp`, `Nrepl`, `Build`, `Formatter` or
`Lint` is loaded by a built application. Those are build-time and tooling
surfaces: they ship in the package, and a request never touches them.

Two consequences worth stating, because both come up in review:

- **A deployed app does load compiler classes**, seven of them. "Production needs
  only `Lang`" is the intuitive answer and it is wrong. The reason is the
  singleton whose fully-qualified name is compiled into build artifacts, which is
  also why it cannot be renamed.
- **The package is not split.** One Composer package carries the compiler, the
  language server and the nREPL server into a production install. Splitting it is
  not a `1.x` change, so this section is the honest answer in the meantime: what
  is *reachable* is broader than what is *loaded*, and the table says which is
  which.

The numbers come from counting declared classes before and after requiring the
entry point, so they follow the code rather than an intention.

## Deprecation policy for 1.x

1. **Announce before removing.** A deprecated symbol ships with a notice for at
   least one full minor, and is removed only in a major.
2. **One channel.** Everything the compiler knows about reports through
   `Phel\Compiler\Domain\Deprecation\DeprecationWarnings`, off by default,
   enabled with `--warn-deprecations` or `PHEL_WARN_DEPRECATIONS=1`. Two
   documented exceptions announce without the flag: a CLI option rename, because
   a renamed flag is one unmissable event, and the `\` namespace separator,
   because it is scheduled for removal at the next major and a notice nobody is
   shown does not keep rule 1's promise
   ([ADR 0006](adr/0006-one-opt-in-deprecation-channel.md),
   [ADR 0014](adr/0014-announce-the-separator-deprecation.md)).
   A deprecation inside a `vendor/` path is never reported: it belongs to the
   dependency's author.
3. **No version promises in the message.** The release such a message names
   inevitably ships and the text goes stale. The tracking issue carries the
   schedule.
4. **A migration page, always.** Every live deprecation appears in
   [the deprecated surface map](migration/deprecated-surface.md) with its
   replacement and a before/after, moving to
   [removed](migration/removed-deprecated-core-fns.md) once gone.
5. **PHP-side deprecations** use `#[\Deprecated]` or `@deprecated`, so
   `phpstan/phpstan-deprecation-rules` reports them downstream.

## PHP support policy

- `1.x` requires **PHP >= 8.4**. Raising the minimum is breaking, major only.
- Every PHP minor from the minimum to the newest stable runs the full compiler
  and core suites in CI, added within one Phel minor of its release.
- Support for a PHP minor is never dropped inside a major, including after it
  leaves PHP's own security window. Phel keeps testing it; the security posture
  of the runtime is the deploying project's call.

## Platform support

| Tier | Platforms | Meaning |
|---|---|---|
| Supported | Linux, macOS | Full compiler, core and PHAR suites run in CI on every push. A failure blocks a release. |
| Best effort | Windows | A reduced suite runs in CI. Bugs are fixed, but a Windows-only failure does not block a release. |

The distinction is about what the project commits to, not about what works. The
platform-sensitive parts are narrow: path separators, `readline` in the REPL, and
the `phel watch` backends, which fall back to polling.

## Configuration surface

`phel-config.php` returns a `Phel\Config\PhelConfig`. Two things are frozen:

- **The wire keys.** `PhelConfig::SRC_DIRS` is the string `'src-dirs'`, and every
  sibling constant is the literal key Gacela reads. Renaming one silently changes
  the meaning of an existing config file, so they are covered by rule 6.
- **The builder API.** `with*()` methods only gain siblings; an existing one keeps
  its name, parameter type and "returns a new instance" contract.

The `.phel/` layout ([project-layout.md](project-layout.md)) is likewise frozen: a
tool may rely on `.phel/cache/` and `.phel/repl-history` being where they are.
`PHEL_DIR` relocates the tree.

## Explicitly not covered

Not under semver, and not before `1.0`:

- The exact PHP source the emitter produces. Only its *behaviour* is promised;
  the test suite pins the text so changes are reviewed, not forbidden.
- Compiler diagnostic wording and error-output shape.
- The `.phel/cache/` file format. Keyed by source hash plus Phel version, so a
  version bump invalidates it by design.
- Anything under `tests/`, `tools/`, `build/` or `resources/`.
- The nREPL and LSP wire protocols beyond the upstream specifications.

## Quality gates behind the promises

| Gate | Where | Fails when |
|---|---|---|
| Public PHP API snapshot | `PublicApiSurfaceTest` | a public signature changes |
| `@internal` annotations | `InternalAnnotationTest` | an internal class is unmarked, or a public one is marked |
| Standard-library snapshot | `CoreApiSurfaceTest` | a definition or arity disappears |
| Special-form list | `LanguageSurfaceSpecTest` | the spec and the analyzer disagree |
| Static analysis | `quality.yml` | PHPStan level 9 or Psalm level 1 reports anything |
| Coverage floor | `coverage.yml` (nightly) | line coverage drops below the floor |
| Benchmark regression | `tests.yml` | a benchmark is >25% slower than the base revision (`phpbench.json` uses 17% locally, where the machine is quiet) |
| Mutation score | `mutation.yml` (weekly) | MSI over `Lang/` and the analyzer drops below the floor |
| Clojure divergences | `run-clojure-test-suite.yml` (nightly) | behaviour changes without the suite being updated |

The coverage and MSI floors are ratchets: raised when a real run clears them
comfortably, never lowered to make a red build green. Currently line coverage
**86.9%** (floor 85) and mutation score **83%** (floor 80) over `Lang/` and the
analyzer; both jobs print the figure to their run summary.

Neither runs per pull request. Coverage takes ~22 minutes and mutation longer, so
on a PR they were the only checks a merge waited on, and both answer questions
("this code is not exercised", "this test asserts nothing") worth knowing on a
schedule rather than within the hour. They still run against `main`, still gate on
their floors, and both take `workflow_dispatch` including a one-off floor
override.

## See also

[Upgrading 0.49 to 1.0](migration/upgrade-0.49-to-1.0.md) ·
[Language surface spec](spec/language-surface.md) ·
[Clojure divergences](spec/clojure-divergences.md) ·
[Deprecated surface](migration/deprecated-surface.md) ·
[Architecture](internals/architecture.md) ·
[Architecture decisions](adr/README.md)
