---
name: module-map
description: 'Gacela wiring, facade boundaries and module-rule upkeep for every src/php module.'
scope: src/php
---

# PHP Modules

Each directory under `src/php/` is a module (roles: `docs/internals/architecture.md`). These conventions apply to all of them; a module's own rule documents only deviations and what the code cannot tell you.

Paths in a module rule are relative to the module directory, except those starting with `src/`, `tests/`, `docs/`, `.agnostic-ai/` or naming a root file.

## Gacela Convention

Unless a module says "No Gacela Pattern":

- `XFacade implements XFacadeInterface`, `XFactory extends AbstractFactory<XConfig>`, `XConfig` reads module settings. Every Facade maps `getFactory()` with `#[ServiceMap]`; every Factory and Provider maps `getConfig()` (`Console` maps to `AbstractConfig`).
- `XProvider extends AbstractProvider`, in a file named `<Module>Provider.php` (Gacela 2.0 resolves pillars by filename suffix), exposes services with `#[Provides(...)]`. Facades are keyed by the Shared contract the consumer asks for: `getProvidedDependency(CompilerFacadeInterface::class)`. String keys only for non-facade services (`CommandProvider::PHP_CONFIG_READER`, `ConsoleProvider::LAZY_COMMANDS`) and `LspProvider::FACADE_LINT`.
- Pillars are internal wiring. Public PHP consumers enter through `Phel\<Module>\<Module>Facade` or the Shared facade contracts.
- Layout: `Application/` (use cases), `Domain/` (interfaces, value objects, logic), `Infrastructure/` (I/O, commands, adapters), `Transfer/` (DTOs); pillars at the module root.
- A non-pillar class using `ServiceResolverAwareTrait` declares BOTH `#[ServiceMap(method: …, className: …)]` and a matching `@method X getFacade()` / `getFactory()` / `getConfig()` docblock. `psalm-gacela.xml` is not included and `phpstan-gacela.neon`'s undefined-method ignores are discarded with `!`, so missing resolver metadata fails instead of becoming `mixed`.

## Facade boundaries

- Interfaces in `Shared/Facade/`: Api, Build, Command, Compiler, Console, Filesystem, Formatter, Interop, Run. No interface (extend `AbstractFacade`): Balance, Fiber, Lint, Lsp, Mutate, Nrepl, Profile, Watch. A module adds its interface once another module injects it (`phel.core` builds `FiberFacade` directly; `Console` wraps Balance's command, never its facade).
- Cross-module access goes through facades; inject `*FacadeInterface`, never a concrete facade. The one exception is `LspFactory::getLintFacade()` (Lint has no contract); `SatelliteFactoryFacadeInjectionTest` fails on a second. A Provider body may name a concrete facade (the locator resolves by class), but binds it under the contract when one exists.
- A Factory may only `new` classes from its own module or `Phel\Shared`. Wanting a `createX()` passthrough on a neighbour facade means the class is a pure utility that belongs in `Phel\Shared`; stateful behaviour stays behind its owner's facade.
- `module-rules.json` (repo root) encodes the boundaries: nothing imports `Console`; `Filesystem` and `Fiber` are leaves; `Compiler` reaches only `Filesystem` outside the shared kernel; `Command` reaches only `Compiler`. PHPStan (`DeclaredModuleDependencyRule`), Psalm (`<moduleRules>` in `psalm.xml`) and `tests/php/Unit/Architecture/ModuleRulesTest` (gacela's `ModuleAssertions`) all enforce it. `phpstan.neon` also runs gacela's `CrossModuleViaFacadeRule`, `CrossModuleMethodCallRule` (the five compiler types `CompilerFacadeInterface` returns are `ignoreReceivers`) and `ServiceMapMissingRule`. Add a rule only once it holds; `Lang`, `Shared` and `Config` are shared kernels and cannot be governed.
- Exactly four cycles: `Api <-> Run` (the only mutual provider pair), `Compiler <-> Shared`, `Lang <-> Shared`, `Phel <-> Run`. Each is documented in its module rule and pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`. A fifth needs a written rationale (ADR 0004).

## Keeping module rules current

Each module has `.agnostic-ai/rules/module-<name>.md` scoped to its directory; a large module adds `module-<name>-<part>.md` scoped to a subdirectory. Edit these sources, never the generated copies, then run `agnostic-ai sync`.

Update the rule when a change adds, removes or re-signs a Facade method, adds or drops a Provider dependency, adds or removes a subdirectory or key class, or changes a constraint. Skip internal refactors, fixes inside existing classes and private methods.

Format: flat in `.agnostic-ai/rules/` with an explicit `scope:` (a subdirectory would override it). Sections: one-line purpose, Gacela pattern (or "No Gacela Pattern"), public API, dependencies, structure, key constraints. Scannable facts, not prose.

```markdown
---
name: module-<name>
description: '<Module> module: one-line purpose.'
scope: src/php/<Module>
---

# <Module> Module
```

"Dependencies" omits `Lang` and `Shared` (every module may use them) and lists everything else, facade or not: each contract or service key the Provider supplies, each non-facade module import (`Config`, a neighbour's exception type), and each edge that closes a documented cycle. Unlisted non-facade edges are how the graph erodes quietly.
