# PHP Modules

Each directory under `src/php/` is a module. The conventions here apply to all of them; each module's own CLAUDE.md documents only deviations and knowledge you cannot derive from the code.

## Gacela Convention

Unless a module says "No Gacela Pattern", it follows this wiring:

- `XFacade implements XFacadeInterface` — `XFactory extends AbstractFactory<XConfig>`; `XConfig` reads module settings.
- `XProvider` exposes cross-module facades via `FACADE_*` string constants (e.g. `RunProvider::FACADE_COMPILER`); the consuming Factory pulls them with `getProvidedDependency(...)`. Modules list these under "Dependencies".

#### What a "Dependencies" section must list

`Lang` and `Shared` are dependency-free leaves that nearly every module imports for types and pure utilities. They are **not** relisted per module; assume they are available. A module's "Dependencies" section covers everything else, whether or not it arrives through a facade:

- every `FACADE_*` the Provider supplies,
- any non-facade module import (e.g. `Config`, or a neighbour's exception type caught for formatting),
- any edge that closes a documented cycle.

Keeping non-facade edges out of the docs is how the graph erodes quietly, so they belong here even when there is no Provider entry to hang them on.
- Layered layout: `Application/` (use cases), `Domain/` (interfaces, value objects, logic), `Infrastructure/` (I/O, CLI commands, adapters), `Transfer/` (DTOs); Gacela files (`Facade`, `Factory`, `Config`, `Provider`) at module root.
- A class using `ServiceResolverAwareTrait` declares BOTH the `#[ServiceMap(method: …, className: …)]` attribute (runtime resolution) and a matching `@method X getFacade()` / `getFactory()` / `getConfig()` docblock (static analysis). The attribute alone leaves the call `mixed` for psalm and phpstan, and that `mixed` cascades through the whole command. Neither `psalm-gacela.xml` nor `phpstan-gacela.neon`'s undefined-method ignore is enabled here, so a missing annotation fails the build.

### Where the FacadeInterface lives

- **`Shared/Facade/`** (dependency inversion): Api, Build, Command, Compiler, Console, Formatter, Interop, Run.
- **Module root**: Fiber, Filesystem (`FiberFacadeInterface`, `FilesystemFacadeInterface`).
- **No interface — extend `AbstractFacade`**: Lint, Lsp, Nrepl, Profile, Watch.

Rules:

- Cross-module access goes through facades only; inject `*FacadeInterface`, never a concrete facade. Exactly one exception survives — `LspFactory::getLintFacade()` — because Lint has no contract yet; `SatelliteFactoryFacadeInjectionTest` fails on a second. `*Provider` classes still name concrete facades: Gacela's locator resolves by class, and the provider is the only place that may.
- A Factory may only `new` classes from its own module or `Phel\Shared`; cross-module instances come via the injected Facade.
- New modules add their `FacadeInterface` to `Shared/Facade/`.
- The module graph has exactly four cyclic pairs (`Api <-> Run`, `Compiler <-> Shared`, `Lang <-> Shared`, `Phel <-> Run`), each documented in the owning module's CLAUDE.md and pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`. `Api <-> Run` is the only mutual Gacela provider pair. Adding a fifth needs a written rationale, not just a green build.

## Module Map

| Module | Role |
|--------|------|
| Api | REPL completion, docs, diagnostics, project index (jump-to-def, references) |
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
| Nrepl | nREPL server (bencode over TCP) |
| Profile | Instrumentation profiler (`phel profile`) |
| Run | Execution, REPL, test runner, CLI commands |
| Shared | Facade interfaces, contracts, pure utilities (leaf, no Gacela) |
| Watch | Hot-reload file watcher |
