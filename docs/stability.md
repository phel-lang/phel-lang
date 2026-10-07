# Stability Policy

**Normative.** What a Phel version number promises, which symbols it covers, and
how those are allowed to change.

<!-- RELEASE-STEP(1.0.0): delete the paragraph below; from 1.0.0 these promises are in force, not a target. -->
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
`src/php/` is internal and carries `@internal`. Internal implementations and
members may change in a patch; names used in public signatures have the opaque
handle guarantee below.

| # | Rule | Examples |
|---|------|----------|
| 1 | The `\Phel` runtime class | `Phel::vector()`, `Phel::bootstrap()` |
| 2 | `Phel\<Module>\<Module>Facade`, except the tooling facades below | `Phel\Compiler\CompilerFacade` |
| 3 | `Phel\<Module>\<Module>FacadeInterface` | None since 0.53.0: every contract lives under `Phel\Shared\Facade\` |
| 4 | Everything under `Phel\Shared\`, except the plumbing below | `Phel\Shared\Facade\CompilerFacadeInterface`, `Phel\Shared\CompileOptions` |
| 5 | Everything under `Phel\Lang\` | `Phel\Lang\Symbol`, `Phel\Lang\Collections\Map\PersistentMapInterface` |
| 6 | Everything under `Phel\Config\` | `Phel\Config\PhelConfig`, `Phel\Config\ProjectLayout` |

Rule 1 covers PHP integrations calling `\Phel`, including the entry point's
`assertBuiltWith()` version check. Generated artifacts are version bound and
must be rebuilt after changing the Phel version. Its `Phel\Phel` base stays
internal, but the members it declares (`bootstrap()`, `run()`, `configFn()` among
them) are reachable through the child and covered as part of `\Phel`.

Rules 4 to 6 cover namespaces, with the explicit Shared exceptions below,
because they are what a consumer cannot avoid: values crossing the facade boundary (`Lang`), the
contracts those facades speak in (`Shared`), and the object a project's
`phel-config.php` constructs (`Config`).

Why the rules take this shape:
[ADR 0021](adr/0021-public-embedding-types-and-internal-tooling.md), which supersedes
[ADR 0005](adr/0005-public-php-api-by-rule-and-snapshot.md).

### Diagnostic codes

The identifiers a program can match on are public, because they exist to be
matched on by a program:

| Surface | Where | Shape |
|---|---|---|
| Lint rule codes | `Phel\Shared\LintRuleCodes` | `phel/unused-require`, `phel/arity-mismatch`, ... |
| Compile and runtime codes | `Phel\Shared\Exceptions\ErrorCode` | `PHEL012`, `PHEL401`, ... |

`phel lint --format=json` writes a lint rule code into its `code` field, or the
error code when a file does not read (`PHEL100` for an unclosed list, `PHEL301`
for an unclosed string), and `phel explain <code>` asks people to type an error
code. Both are contracts with something other than a human reader, so renaming
or removing one is a breaking change and needs a major. Both namespaces are
covered by rule 4 above and pinned by `PublicApiSurfaceTest`, so a rename fails CI
rather than an editor.

What is *not* promised is the message text beside the code. Match on the code.

### Internal by construction

The following remain internal even when a public class returns them:

- `Phel\<Module>\Domain\`, `…\Application\`, `…\Infrastructure\`
- `*Factory`, `*Config`, `*Provider` and `#[ServiceMap]` accessors (Gacela plumbing)
- `Phel\<Module>\Transfer\` (cross-module transfers live in `Phel\Shared\Api\`)

An internal type named in a public signature is an **opaque handle**. A host may
receive it from a public facade and pass it back to that facade. Its type name and
that round trip remain covered by semver; constructing it, inspecting its members
or extending it is unsupported. The snapshot pins those names through the public
signatures. `Phel\Shared\EmitterResult`, `ReaderResult` and `BuildOptions` are
public value objects, so a host can construct and inspect them.

### Tooling and Shared plumbing

The `Lint`, `Mutate`, `Profile`, `Run`, `Fiber`, `Lsp`, `Nrepl`, `Api`, `Balance`
and `Watch` facades are internal CLI plumbing. Their `RunFacadeInterface` and
`ApiFacadeInterface` contracts under `Phel\Shared\Facade\` are internal too.
They remain callable, but their PHP signatures have no semver guarantee. Use
`\Phel::run()` to run a namespace from a PHP host, or the public Compiler,
Build and Interop facades for compilation and export.

These Shared classes are internal despite their namespace:

- `Phel\Shared\Performance\*`
- `Phel\Shared\SourceMap\SupersededSourceMaps`
- `Phel\Shared\Lint\LintRuleExplainerInterface`
- `Phel\Shared\InvocationError`

The same applies to these individual methods on otherwise public classes:
`OptimizationLevel::pin()`, `NoColor::followOutput()`,
`ExistingPaths::reportMissing()`,
`ClassNotFoundHint::javaClassHint()` and
`FrameworkNamespaces::{clojureTarget,isPhel}()`.

Each exception carries `@internal` and is excluded by the snapshot rules.
Generated-code entry points such as `\Phel::fnSlot()`, `Destructure`,
`Symbol::createGenerated()`, `BuildFacade::unresolvedRequireError()` and
`ForeignFn` remain public.

Depending on another internal member is unsupported. Reaching for one signals a
missing public operation, which is worth an issue.

### What counts as a break

Breaking for a public symbol, major only:

- removing a class, interface, method, constant or public property
- narrowing a parameter type, adding a required parameter, reordering parameters
- widening a return type, or changing it to an unrelated type
- adding a method to an interface outside `Phel\Shared\Facade\`, or making an existing method abstract
- changing a class from non-`final` to `final`, or removing a public constructor
- adding a non-private property to a class generated code extends, such as
  `AbstractPersistentStruct` and its parents: a `defstruct`, `defrecord` or
  `deftype` field with that name stops compiling

Not breaking, fine in a minor or patch:

- adding a class, or a method to a `final` class
- adding an optional parameter at the end of a signature
- widening a parameter type, narrowing a return type
- adding a method to an interface under `Phel\Shared\Facade\`
- changes to an `@internal` implementation or member, while preserving opaque
  handles named in public signatures

Interfaces under `Phel\Shared\Facade\` are a contract for callers and type
hints, not for implementers. Every cross-module call goes through them (ADR
0003), so they gain methods in most minor releases, and each one is implemented
only by Phel's own facade. Implementing one outside Phel is unsupported. The
changelog still labels such an addition **BREAKING (PHP API, implementers
only)**, so anyone who implemented one anyway sees it.

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
a stock `phel init` project at `0.54.0-beta`, `require`-ing the built entry point
declares classes from these places and no others, Composer's autoloader aside:

| Namespace | Classes | Why |
|---|---|---|
| `Phel\Lang\` | 731 | 702 compiled fns, one anonymous `AbstractFn` subclass each, almost all from the standard library. The other 29 are the runtime: persistent collections, `AbstractFn`, `Registry` |
| `Gacela\` | 49 | `\Phel::bootstrap()`, which the entry point calls so that core fns such as `read-string`, `eval` and `promise` can reach Phel's facades |
| `Phel\Compiler\` | 8 | `GlobalEnvironmentSingleton`, the environment and registry it resolves through, and the two resolvers and three warners that environment builds. The emitter bakes that FQN into every compiled file, so it is an ABI shim rather than the compiler running |
| `Phel\Config\` | 5 | `PhelConfig` and its reader, loaded by the bootstrap to read `phel-config.php` |
| `Phel\Shared\` | 5 | `Munge` and the printer reached from generated code, plus two helpers the bootstrap uses |
| `\Phel`, `Phel\Phel` | 2 | `\Phel` carries `addDefinition()` / `getDefinition()`, which every compiled file calls. It extends `Phel\Phel`, which carries `bootstrap()` and `setupRuntimeArgs()`, the two calls the entry point makes |

Nothing from `Run`, `Console`, `Api`, `Lsp`, `Nrepl`, `Build`, `Formatter` or
`Lint` is loaded by a built application. Those are build-time and tooling
surfaces: they ship in the package, and a request never touches them.

Two consequences worth stating, because both come up in review:

- **A deployed app does load compiler classes**, eight of them. "Production needs
  only `Lang`" is the intuitive answer and it is wrong. The reason is the
  singleton whose fully-qualified name is compiled into build artifacts. It can
  change between Phel versions because those artifacts must be rebuilt.
- **The package is not split.** One Composer package carries the compiler, the
  language server and the nREPL server into a production install. Splitting it is
  not a `1.x` change, so this section is the honest answer in the meantime: what
  is *reachable* is broader than what is *loaded*, and the table says which is
  which.

The numbers come from diffing `get_declared_classes()` before and after requiring
`out/index.php`, grouped by namespace prefix; interfaces and traits are not
counted. They follow the code rather than an intention. Without the bootstrap call
the `Gacela\` and `Phel\Config\` rows drop out and `Phel\Shared\` falls to 3.

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

- `1.x` requires **PHP >= 8.5**. Raising the minimum is breaking, major only.
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
  sibling constant is the literal key the config reader consumes. Renaming one
  silently changes the meaning of an existing config file, so they are covered
  by rule 6. The Gacela mechanics that load those keys stay internal.
- **The builder API.** `with*()` methods only gain siblings; an existing one keeps
  its name, parameter type and "returns a new instance" contract.

The `.phel/` layout ([project-layout.md](project-layout.md)) is likewise frozen: a
tool may rely on `.phel/cache/` and `.phel/repl-history` being where they are.
`PHEL_DIR` relocates the tree.

## Explicitly not covered

Not under semver, in any release:

- Build output (`out/`) and generated export wrappers across Phel versions,
  including patch releases and development commits. They carry the resolved
  building version; generated entry
  points and wrappers reject a different runtime version before executing the
  program. Run `phel build` after changing Phel, or `phel export` for wrappers.
  Source remains covered by the language promise. This rule also requires
  rebuilding older artifacts that predate the version check.
- The exact PHP source the emitter produces. Only its *behaviour* is promised;
  the test suite pins the text so changes are reviewed, not forbidden.
- Compiler diagnostic *wording*, and the layout of human-facing error output.
  The machine-readable parts are covered: see "Diagnostic codes" above.
- The `.phel/cache/` file format. Keyed by source hash plus optimization level,
  Phel version and the fingerprint of the declared `cache-env-vars`, so a
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
| Benchmark regression | `tests.yml` | a benchmark is >25% slower than the base revision, the tolerance `phpbench.json` sets for CI and local runs alike |
| Mutation score | `mutation.yml` (weekly) | MSI over `Lang/` and the analyzer drops below the floor |
| Clojure divergences | `run-clojure-test-suite.yml` (nightly) | behaviour changes without the suite being updated |

The coverage and MSI floors are ratchets: raised when a real run clears them
comfortably, never lowered to make a red build green. Currently line coverage
**86.36%** (floor 85) and mutation score **82%** (floor 80) over `Lang/` and the
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
