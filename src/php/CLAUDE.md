# PHP Modules

Each directory under `src/php/` is a module. The conventions here apply to all of them; each module's own CLAUDE.md documents only deviations and knowledge you cannot derive from the code.

## Gacela Convention

Unless a module says "No Gacela Pattern", it follows this wiring:

- `XFacade implements XFacadeInterface` — `XFactory extends AbstractFactory<XConfig>`; `XConfig` reads module settings.
- `XProvider` exposes cross-module facades via `FACADE_*` string constants (e.g. `RunProvider::FACADE_COMPILER`); the consuming Factory pulls them with `getProvidedDependency(...)`. Modules list these under "Dependencies".
- Layered layout: `Application/` (use cases), `Domain/` (interfaces, value objects, logic), `Infrastructure/` (I/O, CLI commands, adapters), `Transfer/` (DTOs); Gacela files (`Facade`, `Factory`, `Config`, `Provider`) at module root.

### Where the FacadeInterface lives

- **`Shared/Facade/`** (dependency inversion): Api, Build, Command, Compiler, Console, Formatter, Interop, Run.
- **Module root**: Fiber, Filesystem (`FiberFacadeInterface`, `FilesystemFacadeInterface`).
- **No interface — extend `AbstractFacade`**: Lint, Lsp, Nrepl, Profile, Watch.

Rules:

- Cross-module access goes through facades only; inject `*FacadeInterface`, never a concrete facade.
- A Factory may only `new` classes from its own module or `Phel\Shared`; cross-module instances come via the injected Facade.
- New modules add their `FacadeInterface` to `Shared/Facade/`.

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
