# Api Module

REPL autocompletion, function introspection/docs, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point, PHP-interop tooling).

## Public API (Facade)

| Method | Purpose |
|--------|---------|
| `replComplete(string)` / `replCompleteWithTypes(string)` | REPL autocompletion, plain or with type info (nREPL) |
| `getPhelFunctions(list<string> = [])` | All public Phel functions, optionally filtered by namespace |
| `analyzeSource(string source, string uri): list<Diagnostic>` | Parse + analyze, return diagnostics |
| `findSymbolMetadata(string, string currentNs = 'user'): ?PhelFunction` | Symbol lookup in registry + static catalog |
| `completionDoc(string candidate, string currentNs = 'user'): ?string` | Doc markdown for a completion candidate |
| `extractDefinitions(string source, string uri)` | Definitions from one source |
| `indexProject(list<string> srcDirs): ProjectIndex` | Build project symbol index |
| `resolveSymbol(ProjectIndex, ns, symbol): ?Definition` | Jump-to-def |
| `findReferences(ProjectIndex, ns, symbol)` | Find-references |
| `completeAtPoint(source, line, col, ProjectIndex)` | Completion at cursor; returns PHP-interop completions in a `php/`-interop position, else Phel completions |
| `phpInteropHoverAt(source, line, col): ?string` | Reflected hover markdown for PHP interop |
| `phpInteropSignatureAt(source, line, col): ?array` | LSP signature help for PHP interop |
| `phelSignatureAt(source, line, col, currentNs = 'user'): ?array` | LSP signature help for a plain Phel function call (`PhelSignatureResolver`) |
| `createApiDaemon(): ApiDaemon` | Long-running JSON-RPC daemon |

Every method above except `createApiDaemon` is declared on `Phel\Shared\Facade\ApiFacadeInterface`, so `Lint`, `Lsp`, `Nrepl`, `Run` and `Watch` all inject the contract. `createApiDaemon` stays off it: `ApiDaemon` is this module's own stdio adapter, consumed only by `ApiDaemonCommand`, so exporting it would put an `Infrastructure` class in a leaf contract for no consumer.

Every transfer the contract names lives in `Phel\Shared\Api`: `ProjectIndex`, `Definition`, `Location`, `Completion`, `Diagnostic`, `PhelFunction`, `CompletionResultTransfer`. `Transfer/` keeps only the PHP-interop reflection types (`PhpInteropCall`, `PhpInteropClass`, `PhpInteropContext`, `PhpInteropSignature`), which never cross the facade.

## Dependencies

- Run (namespace resolution, directory listing) — `FACADE_RUN`.
- Compiler (lex, parse, read, analyze phases) — `FACADE_COMPILER`.
- `ApiConfig::allNamespaces()` lists the 25 documented Phel namespaces; `ApiConfig::githubRef()` returns `VersionFinder::LATEST_VERSION`.

`Api <-> Run` is the codebase's only mutual Gacela provider pair, and the cycle is a wiring detail rather than a structural one: both sides consume each other through `Phel\Shared\Facade\*Interface`, and the concrete facades appear only in `ApiProvider` / `RunProvider`, because Gacela's locator has to name a class. `ModuleDependencyCycleTest` pins exactly those two files.

`ApiProvider` / `RunProvider` and the peer providers in `Lint`, `Lsp`, `Nrepl` and `Watch` still name the concrete `ApiFacade`, because Gacela's locator resolves by class. That is wiring, not coupling: every factory getter and every collaborator behind it types `ApiFacadeInterface`.

## PHP Interop Tooling (`Application/Php*`)

All collaborators degrade to empty/null on unknown types or reflection failure.

| Class | Role |
|-------|------|
| `PhpInteropReflector` | Reflection + composer classmap. `methodSignatureInfo`/`functionSignatureInfo` → `PhpInteropSignature` (per-param labels + phpdoc); `instanceMemberInfo`/`staticMemberInfo` → property/constant/enum-case hover; `classInfo` → `PhpInteropClass` (kind/parent/interfaces/constructor); `methodReturnType` walks return type for chains; `isInstantiable` guards `php/new` help (interfaces/abstract/enums); `classNames` includes interfaces; `staticMembers` labels enum cases |
| `PhpInteropContextResolver` | Lexical receiver-type resolution from `:tag` / inline `php/new` / binding, `(:use ...)`/`(use ...)` aliases incl. `:as`, multi-line via `CursorText::before`; walks multi-hop `php/->` chains, factory `(php/:: \Foo make)` bindings, indirect `let` rebinds |
| `PhpFormTokenizer` | Shared top-level token splitter (used by scanner + resolver) |
| `PhpInteropCallScanner` | Balanced-paren scan for the call enclosing the cursor + its `activeParameter`; fixes chained `(php/-> x (a) (b ⟂` |
| `PhpInteropCompleter`, `PhpInteropDocResolver` | Interop completion + doc resolution |

## Key Constraints

- `SourceAnalyzer` runs a pipeline of `list<AnalysisStageInterface>` (`Application/Analysis/`: Preload → LexAndParse → ReadAndAnalyze); add/remove stages in `ApiFactory::createSourceAnalyzer()`.
- `ReadAndAnalyzeStage` wraps its pass in `GlobalEnvironment::enterAnalysisMode()`/`leaveAnalysisMode()`. `PreloadDependenciesStage` really evaluates the bundled `phel.*` modules and the file's dependencies, so the namespace under analysis is usually already bound; without that guard every top-level `def` raises `DuplicateDefinitionException` and kills the run. Any new stage that re-analyzes loaded sources needs the same guard.
- Analysis routes through `CompilerFacade` phases only — never bypass.
- `Infrastructure/NativeSymbolCatalog`: static doc table for special forms / built-ins with no `.phel` source. Special forms (`load`, `in-ns`, `use`) need an entry here to appear in `phel doc`. `PhelFnLoader` merges it with runtime metadata.
- `ProjectIndexer` re-indexes from scratch; caching hook is at the `SymbolExtractor` call-site.
- `ReplCompleter` lazy-loads Phel functions, caches the PHP builtin catalog.
- `PhelFnNormalizer` normalizes Phel function metadata with group keys + GitHub ref.
