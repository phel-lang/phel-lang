# PHP Modules

Each directory under `src/php/` is a module. The conventions here apply to all of them; each module's own CLAUDE.md documents only deviations and knowledge you cannot derive from the code.

## Gacela Convention

Unless a module says "No Gacela Pattern", it follows this wiring:

- `XFacade implements XFacadeInterface` — `XFactory extends AbstractFactory<XConfig>`; `XConfig` reads module settings. Every Facade explicitly maps `getFactory()` to `XFactory` with `#[ServiceMap]`; every Factory and Provider maps `getConfig()` to `XConfig` (`Console` maps to `AbstractConfig`).
- `XProvider extends AbstractProvider` and exposes cross-module services with `#[Provides(...)]`. Facade dependencies are keyed by the Shared contract the consumer asks for (e.g. `CompilerFacadeInterface::class`), and the consuming Factory pulls them with `getProvidedDependency(CompilerFacadeInterface::class)`. String keys remain only for non-facade services (`CommandProvider::PHP_CONFIG_READER`, `ConsoleProvider::LAZY_COMMANDS`) and the one concrete-facade exception (`LspProvider::FACADE_LINT`).
- `Facade`, `Factory`, `Config` and `Provider` are internal wiring. Public PHP consumers should enter through `Phel\<Module>\<Module>Facade` or the documented Shared facade contracts, not depend on Gacela pillars directly.

#### What a "Dependencies" section must list

`Lang` and `Shared` are dependency-free leaves that nearly every module imports for types and pure utilities. They are **not** relisted per module; assume they are available. A module's "Dependencies" section covers everything else, whether or not it arrives through a facade:

- every facade contract or non-facade service key the Provider supplies,
- any non-facade module import (e.g. `Config`, or a neighbour's exception type caught for formatting),
- any edge that closes a documented cycle.

Keeping non-facade edges out of the docs is how the graph erodes quietly, so they belong here even when there is no Provider entry to hang them on.
- Layered layout: `Application/` (use cases), `Domain/` (interfaces, value objects, logic), `Infrastructure/` (I/O, CLI commands, adapters), `Transfer/` (DTOs); Gacela files (`Facade`, `Factory`, `Config`, `Provider`) at module root.
- A non-pillar class using `ServiceResolverAwareTrait` declares BOTH the `#[ServiceMap(method: …, className: …)]` attribute and a matching `@method X getFacade()` / `getFactory()` / `getConfig()` docblock. Gacela 2.0's PHPStan and Psalm integrations type the attribute; the docblock remains for IDEs and redundancy. `psalm-gacela.xml` is not included, and the undefined-method ignores from `phpstan-gacela.neon` are discarded with `!`, so missing resolver metadata fails instead of falling back to `mixed`.

### Where the FacadeInterface lives

- **`Shared/Facade/`** (dependency inversion): Api, Build, Command, Compiler, Console, Formatter, Interop, Run.
- **Module root**: Fiber, Filesystem (`FiberFacadeInterface`, `FilesystemFacadeInterface`). `FilesystemFacadeInterface` carries gacela's `#[PublicApi]`, so referencing it from another module's Factory/Provider is not a `crossModuleWithoutFacade` finding; moving it to Shared waits for the next major (#2870).
- **No interface — extend `AbstractFacade`**: Balance, Lint, Lsp, Mutate, Nrepl, Profile, Watch.

Rules:

- Cross-module access goes through facades only; inject `*FacadeInterface`, never a concrete facade. Exactly one exception survives (`LspFactory::getLintFacade()`), because Lint has no contract yet; `SatelliteFactoryFacadeInjectionTest` fails on a second. `*Provider` methods may name concrete facades only in their bodies because Gacela's locator resolves by class; the provided binding id should still be the consumer-facing contract whenever one exists.
- A Factory may only `new` classes from its own module or `Phel\Shared`; cross-module instances come via the injected Facade.
- New modules add their `FacadeInterface` to `Shared/Facade/` when another module injects them. `Balance` has none because nothing does: `Console` wraps its command, it never injects the facade.
- `module-rules.json` at the repo root is the machine-readable half of the boundaries stated in prose here and in the per-module CLAUDE.md files: nothing imports `Console`, `Filesystem` and `Fiber` are leaves, `Compiler` reaches only `Filesystem` outside the shared kernel, `Command` reaches only `Compiler`. Both analysers read it (`DeclaredModuleDependencyRule` in `phpstan.neon`, `<moduleRules>` in `psalm.xml`), and `tests/php/Unit/Architecture/ModuleRulesTest` judges the same file with gacela's `ModuleAssertions`, so a new import that breaks one of those sentences fails the build instead of contradicting a paragraph. `phpstan.neon` also runs gacela's `CrossModuleViaFacadeRule` and `CrossModuleMethodCallRule` (the latter with the five compiler types `CompilerFacadeInterface` returns as `ignoreReceivers`) and `ServiceMapMissingRule`. Add a rule only once it already holds; `Lang`, `Shared` and `Config` cannot be governed, being declared shared kernels.
- The module graph has exactly four cyclic pairs (`Api <-> Run`, `Compiler <-> Shared`, `Lang <-> Shared`, `Phel <-> Run`), each documented in the owning module's CLAUDE.md and pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`. `Api <-> Run` is the only mutual Gacela provider pair. Adding a fifth needs a written rationale, not just a green build.

## Module Map

| Module | Role |
|--------|------|
| Api | REPL completion, docs, diagnostics, project index (jump-to-def, references) |
| Balance | Delimiter repair (`phel balance`); the one module that writes a file it could not parse |
| Build | Compile Phel projects to PHP: dependency order, caching, namespace extraction |
| Command | Error reporting, exception formatting, directory discovery |
| Compiler | Pipeline: lexer → parser → reader → analyzer → simplifier → emitter |
| Config | `PhelConfig` data model (leaf, no Gacela) |
| Console | CLI entry point; registers commands from all modules |
| Fiber | Promises, futures, cooperative scheduler for `phel.core` async |
| Filesystem | Temp dir management, compiled artifact tracking |
| Formatter | Code formatter (`phel format`) |
| HttpClient | Stream transport for `phel.http-client` (leaf, no Gacela) |
| Interop | PHP wrapper generation for `^{:export true}` fns |
| Lang | Runtime types, persistent collections (leaf, no Gacela) |
| Lint | Read-only semantic linter |
| Lsp | LSP v3.17 server over stdio |
| Mutate | Mutation testing for Phel code (`phel mutate`): CST mutators, a worker subprocess that redefines one `defn` per mutant and runs the tests |
| Nrepl | nREPL server (bencode over TCP) |
| Profile | Instrumentation profiler (`phel profile`) |
| Run | Execution, REPL, test runner, CLI commands |
| Shared | Facade interfaces, contracts, pure utilities (leaf, no Gacela) |
| Watch | Hot-reload file watcher |
