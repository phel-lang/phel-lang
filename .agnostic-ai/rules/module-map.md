---
name: module-map
description: 'Gacela wiring, facade boundaries and module-rule upkeep for every src/php module.'
scope: src/php
---

# PHP Modules

Each directory under `src/php/` is a module. The conventions here apply to all of them; each module's own rule (`.agnostic-ai/rules/module-<name>.md`) documents only deviations and knowledge you cannot derive from the code.

Paths in a module rule are relative to that module's directory (`src/php/` for this one). A path that starts with `src/`, `tests/`, `docs/`, `.agnostic-ai/` or names a root file is relative to the repository root.

## Gacela Convention

Unless a module says "No Gacela Pattern", it follows this wiring:

- `XFacade implements XFacadeInterface`; `XFactory extends AbstractFactory<XConfig>`; `XConfig` reads module settings. Every Facade explicitly maps `getFactory()` to `XFactory` with `#[ServiceMap]`; every Factory and Provider maps `getConfig()` to `XConfig` (`Console` maps to `AbstractConfig`).
- `XProvider extends AbstractProvider` (the file must be named `<Module>Provider.php`: Gacela 2.0 resolves pillars by filename suffix) and exposes cross-module services with `#[Provides(...)]`. Facade dependencies are keyed by the Shared contract the consumer asks for (e.g. `CompilerFacadeInterface::class`), and the consuming Factory pulls them with `getProvidedDependency(CompilerFacadeInterface::class)`. String keys remain only for non-facade services (`CommandProvider::PHP_CONFIG_READER`, `ConsoleProvider::LAZY_COMMANDS`) and the one concrete-facade exception (`LspProvider::FACADE_LINT`).
- `Facade`, `Factory`, `Config` and `Provider` are internal wiring. Public PHP consumers should enter through `Phel\<Module>\<Module>Facade` or the documented Shared facade contracts, not depend on Gacela pillars directly.

- Layered layout: `Application/` (use cases), `Domain/` (interfaces, value objects, logic), `Infrastructure/` (I/O, CLI commands, adapters), `Transfer/` (DTOs); Gacela files (`Facade`, `Factory`, `Config`, `Provider`) at module root.
- A non-pillar class using `ServiceResolverAwareTrait` declares BOTH the `#[ServiceMap(method: …, className: …)]` attribute and a matching `@method X getFacade()` / `getFactory()` / `getConfig()` docblock. Gacela 2.0's PHPStan and Psalm integrations type the attribute; the docblock remains for IDEs and redundancy. `psalm-gacela.xml` is not included, and the undefined-method ignores from `phpstan-gacela.neon` are discarded with `!`, so missing resolver metadata fails instead of falling back to `mixed`.

### Where the FacadeInterface lives

- **`Shared/Facade/`** (dependency inversion): Api, Build, Command, Compiler, Console, Filesystem, Formatter, Interop, Run.
- **No interface, extend `AbstractFacade`**: Balance, Fiber, Lint, Lsp, Mutate, Nrepl, Profile, Watch. `phel.core` builds `FiberFacade` directly; no module injects it.

Rules:

- Cross-module access goes through facades only; inject `*FacadeInterface`, never a concrete facade. Exactly one exception survives (`LspFactory::getLintFacade()`), because Lint has no contract yet; `SatelliteFactoryFacadeInjectionTest` fails on a second. `*Provider` methods may name concrete facades only in their bodies because Gacela's locator resolves by class; the provided binding id should still be the consumer-facing contract whenever one exists.
- A Factory may only `new` classes from its own module or `Phel\Shared`; cross-module instances come via the injected Facade. A pure stateless utility belongs in `Phel\Shared` (`Munge`, `ColorStyle`); stateful behaviour stays behind its owner's facade. Wanting a `createX()` passthrough on a neighbour facade so another module can `new` something means the class is a Shared utility: move it there.
- New modules add their `FacadeInterface` to `Shared/Facade/` when another module injects them. `Balance` has none because nothing does: `Console` wraps its command, it never injects the facade.
- `module-rules.json` at the repo root is the machine-readable half of the boundaries stated in prose here and in the per-module rules: nothing imports `Console`, `Filesystem` and `Fiber` are leaves, `Compiler` reaches only `Filesystem` outside the shared kernel, `Command` reaches only `Compiler`. Both analysers read it (`DeclaredModuleDependencyRule` in `phpstan.neon`, `<moduleRules>` in `psalm.xml`), and `tests/php/Unit/Architecture/ModuleRulesTest` judges the same file with gacela's `ModuleAssertions`, so a new import that breaks one of those sentences fails the build instead of contradicting a paragraph. `phpstan.neon` also runs gacela's `CrossModuleViaFacadeRule` and `CrossModuleMethodCallRule` (the latter with the five compiler types `CompilerFacadeInterface` returns as `ignoreReceivers`) and `ServiceMapMissingRule`. Add a rule only once it already holds; `Lang`, `Shared` and `Config` cannot be governed, being declared shared kernels.
- The module graph has exactly four cyclic pairs (`Api <-> Run`, `Compiler <-> Shared`, `Lang <-> Shared`, `Phel <-> Run`), each documented in the owning module's rule and pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`. `Api <-> Run` is the only mutual Gacela provider pair. Adding a fifth needs a written rationale, not just a green build.

## Module list

Roles of every module: `docs/internals/architecture.md`. Each module's purpose heads its own rule.

## Keeping module rules current

Each module has `.agnostic-ai/rules/module-<name>.md`, scoped to its directory; large modules add `module-<name>-<part>.md` scoped to a subdirectory. Edit these sources, never the generated copies, then run `agnostic-ai sync`.

Update the rule when a change adds, removes or re-signs a Facade method, adds or drops a Provider dependency, adds or removes a subdirectory or key class, or changes a constraint (for example a new special form). Skip it for internal refactors, bug fixes inside existing classes, and private methods.

Format: flat in `.agnostic-ai/rules/` with an explicit `scope:` (a subdirectory would override it). Sections in order: one-line purpose, Gacela pattern (or "No Gacela Pattern" for a leaf), public API, dependencies, structure, key constraints. Scannable facts, not prose.

```markdown
---
name: module-<name>
description: '<Module> module: one-line purpose.'
scope: src/php/<Module>
---

# <Module> Module
```

### What a "Dependencies" section must list

`Lang` and `Shared` are dependency-free leaves that nearly every module imports for types and pure utilities. They are **not** relisted per module; assume they are available. A module's "Dependencies" section covers everything else, whether or not it arrives through a facade:

- every facade contract or non-facade service key the Provider supplies,
- any non-facade module import (e.g. `Config`, or a neighbour's exception type caught for formatting),
- any edge that closes a documented cycle.

Keeping non-facade edges out of the docs is how the graph erodes quietly, so they belong here even when there is no Provider entry to hang them on.
