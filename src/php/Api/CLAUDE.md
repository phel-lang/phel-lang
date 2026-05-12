# Api Module

Tooling support layer: REPL autocompletion, function introspection, documentation, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point).

## Gacela Pattern

- **Facade**: `ApiFacade` implements `ApiFacadeInterface`
- **Factory**: `ApiFactory` extends `AbstractFactory<ApiConfig>`
- **Config**: `ApiConfig` : lists all documented namespaces (`phel.core`, `phel.string`, etc.)
- **Provider**: `ApiProvider` : injects `RunFacade` (`FACADE_RUN`) and `CompilerFacade` (`FACADE_COMPILER`)

## Public API (Facade)

- `replComplete(string): array` : basic REPL autocompletion
- `replCompleteWithTypes(string): array` : extended completion with type info
- `getPhelFunctions(array = []): array` : all public Phel functions
- `findSymbolMetadata(string, string = 'user'): ?PhelFunction` : lookup symbol in registry + static catalog
- `analyzeSource(string, string): array` : semantic diagnostics from parse + analyze
- `indexProject(array): ProjectIndex` : project-level symbol table + references
- `resolveSymbol(ProjectIndex, string, string): ?Definition` : jump to definition
- `findReferences(ProjectIndex, string, string): array` : reverse index lookup
- `completeAtPoint(string, int, int, ProjectIndex): array` : context-aware completion
- `createApiDaemon(): ApiDaemon` : long-running JSON-RPC daemon

## Dependencies

- **Run** (`RunFacade`) : namespace resolution, directory listing
- **Compiler** (`CompilerFacade`) : lex, parse, read, analyze phases

## Structure

```
Api/
├── Application/        Analysis stages, SourceAnalyzer, ReplCompleter, SymbolExtractor,
│                       ProjectIndexer, SymbolResolver, ReferenceFinder, PointCompleter
├── Domain/             Interfaces, exception types
├── Infrastructure/     Command/ (Doc, Analyze, Index, ApiDaemon), Daemon/
├── Transfer/           PhelFunction, CompletionResultTransfer, Diagnostic, etc.
└── Gacela files        ApiFacade, ApiFactory, ApiConfig, ApiProvider
```

## Key Constraints

- `SourceAnalyzer` is a pipeline of `AnalysisStageInterface` : add stages in `ApiFactory::createSourceAnalyzer()`
- `ProjectIndexer` re-indexes from scratch (no file-hash caching); hook for caching at `SymbolExtractor` call-site
- `ReplCompleter` lazy-loads Phel functions and caches PHP functions/classes
- `PhelFnLoader` provides metadata for ~40 native symbols/special forms
- All semantic analysis routes through `CompilerFacade` phases (lex, parse, read, analyze)
