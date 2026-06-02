# Api Module

REPL autocompletion, function introspection, documentation, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point).

## Gacela Pattern

- **Facade**: `ApiFacade` implements `ApiFacadeInterface`
- **Factory**: `ApiFactory` extends `AbstractFactory<ApiConfig>`
- **Config**: `ApiConfig.allNamespaces()` lists 24 documented namespaces; `githubRef()` returns latest version
- **Provider**: `ApiProvider` injects `RunFacade` and `CompilerFacade`

## Public API (Facade)

| Method | Returns | Purpose |
|--------|---------|---------|
| `replComplete(string)` | `list<string>` | Basic REPL autocompletion |
| `replCompleteWithTypes(string)` | `list<CompletionResultTransfer>` | Completion with type info for nREPL |
| `getPhelFunctions(list<string> = [])` | `list<PhelFunction>` | All public Phel functions, optionally filtered by namespace |
| `analyzeSource(string, string)` | `list<Diagnostic>` | Parse and analyze, return diagnostics |
| `findSymbolMetadata(string, string = 'user')` | `?PhelFunction` | Symbol lookup in registry and static catalog |
| `indexProject(list<string>)` | `ProjectIndex` | Build project-level symbol index from source dirs |
| `resolveSymbol(ProjectIndex, string, string)` | `?Definition` | Jump to definition |
| `findReferences(ProjectIndex, string, string)` | `list<Location>` | Find all reference sites of a symbol |
| `completeAtPoint(string, int, int, ProjectIndex)` | `list<Completion>` | Context-aware completion (locals, project defs, phel.core) |
| `createApiDaemon()` | `ApiDaemon` | Long-running JSON-RPC daemon |

## Dependencies

- **Run** (RunFacade): namespace resolution, directory listing
- **Compiler** (CompilerFacade): lex, parse, read, analyze phases

## Structure

```
Api/
├── Application/
│   ├── Analysis/              Pluggable analysis stages (LexAndParseStage, ReadAndAnalyzeStage, etc.)
│   ├── SourceAnalyzer          Pipeline runner for analysis stages
│   ├── ReplCompleter, PointCompleter, SymbolMetadataFinder
│   ├── ProjectIndexer, SymbolResolver, ReferenceFinder, SymbolExtractor
│   └── PhelFnNormalizer, PhelFnGroupKeyGenerator, PhpSymbolCatalog
├── Domain/                     Interface contracts and exception types
├── Infrastructure/
│   ├── Command/                CLI commands (Doc, Analyze, Index)
│   ├── Daemon/                 ApiDaemon (JSON-RPC server)
│   ├── phel/                   Phel-native helpers
│   ├── PhelFnLoader            Merges runtime + native symbol metadata (thin loader)
│   └── NativeSymbolCatalog     Static doc table for special forms / built-ins (no .phel source)
├── Transfer/                   DTO contracts (PhelFunction, Diagnostic, Definition, ProjectIndex, etc.)
└── Gacela files                ApiFacade, ApiFactory, ApiConfig, ApiProvider
```

## Key Constraints

- `SourceAnalyzer` takes `list<AnalysisStageInterface>` (add/remove in `ApiFactory::createSourceAnalyzer()`)
- `ProjectIndexer` re-indexes from scratch; caching hook at `SymbolExtractor` call-site
- `ReplCompleter` lazy-loads Phel functions, caches PHP builtin catalog
- `PhelFnNormalizer` normalizes Phel function metadata with group keys and GitHub ref
- Analysis routes through `CompilerFacade` phases only
