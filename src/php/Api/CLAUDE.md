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
| `createApiDaemon(): ApiDaemon` | Long-running JSON-RPC daemon |

Project-level transfers: `ProjectIndex`, `Definition`, `Location`, `Completion`, `Diagnostic`, `PhelFunction`.

## Dependencies

- Run (namespace resolution, directory listing) — `FACADE_RUN`.
- Compiler (lex, parse, read, analyze phases) — `FACADE_COMPILER`.
- `ApiConfig::allNamespaces()` lists the 25 documented Phel namespaces; `ApiConfig::githubRef()` returns `VersionFinder::LATEST_VERSION`.

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
- Analysis routes through `CompilerFacade` phases only — never bypass.
- `Infrastructure/NativeSymbolCatalog`: static doc table for special forms / built-ins with no `.phel` source. Special forms (`load`, `in-ns`, `use`) need an entry here to appear in `phel doc`. `PhelFnLoader` merges it with runtime metadata.
- `ProjectIndexer` re-indexes from scratch; caching hook is at the `SymbolExtractor` call-site.
- `ReplCompleter` lazy-loads Phel functions, caches the PHP builtin catalog.
- `PhelFnNormalizer` normalizes Phel function metadata with group keys + GitHub ref.
