# Api Module

Tooling support layer: REPL autocompletion, function introspection, documentation, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point).

## Gacela Pattern

- **Facade**: `ApiFacade` implements `ApiFacadeInterface`
- **Factory**: `ApiFactory` extends `AbstractFactory<ApiConfig>`
- **Config**: `ApiConfig` — lists all documented namespaces (`phel\core`, `phel\string`, etc.)
- **Provider**: `ApiProvider` — injects `RunFacade` (`FACADE_RUN`) and `CompilerFacade` (`FACADE_COMPILER`)

## Public API (Facade)

**Documentation & Completion**
- `replComplete(string $input): list<string>` — basic REPL autocompletion
- `replCompleteWithTypes(string $input): list<CompletionResultTransfer>` — extended completion with type annotations
- `getPhelFunctions(array $namespaces = []): list<PhelFunction>` — all public Phel functions

**User-code Semantic Analysis**
- `analyzeSource(string $source, string $uri): list<Diagnostic>` — run Parser + Analyzer, return semantic diagnostics
- `indexProject(list<string> $srcDirs): ProjectIndex` — project-level symbol table + references
- `resolveSymbol(ProjectIndex, string $namespace, string $symbol): ?Definition` — jump to definition
- `findReferences(ProjectIndex, string $namespace, string $symbol): list<Location>` — reverse index
- `completeAtPoint(string $source, int $line, int $col, ProjectIndex): list<Completion>` — context-aware completion (locals + project defs + phel-core)
- `createApiDaemon(): ApiDaemon` — long-running JSON-RPC daemon (stdio)

## CLI Commands

- `./bin/phel doc` — browse core function docs
- `./bin/phel analyze <file>` — JSON diagnostics for a single file
- `./bin/phel index <dir>... [--out=file.json]` — build project index summary (optionally persist)
- `./bin/phel api-daemon` — long-running JSON-RPC daemon over stdio

## Dependencies

- **Run** (`RunFacade`) — namespace resolution, directory listing, file evaluation
- **Compiler** (`CompilerFacade`) — lex, parse, read, analyze used by semantic analysis
- **Lang** (`Phel`, `Keyword`, `Symbol`, `FnInterface`) — runtime type introspection

## Structure

```
Api/
├── Application/
│   ├── Analysis/              LexAndParseStage, ReadAndAnalyzeStage
│   ├── PhelFnGroupKeyGenerator, PhelFnNormalizer, ReplCompleter
│   ├── SourceAnalyzer         pipeline runner over AnalysisStageInterface
│   ├── SymbolExtractor        per-file def/ref extractor (read + traverse forms)
│   ├── ProjectIndexer         aggregates SymbolExtractor output across dirs
│   ├── SymbolResolver         jump-to-def on ProjectIndex
│   ├── ReferenceFinder        reverse index lookup on ProjectIndex
│   └── PointCompleter         context-aware completion at a source point
├── Domain/                    interfaces for each Application class
├── Infrastructure/
│   ├── Command/               DocCommand, AnalyzeCommand, IndexCommand, ApiDaemonCommand
│   ├── Daemon/                ApiDaemon, JsonRpcFraming, JsonRpcDispatcher, UnknownMethodException
│   └── PhelFnLoader           native symbol docs + runtime fn loading
├── Transfer/
│   ├── PhelFunction, CompletionResultTransfer
│   └── Diagnostic, Definition, Location, Completion, ProjectIndex
└── Gacela files               ApiFacade, ApiFactory, ApiConfig, ApiProvider
```

## Key Constraints

- `ReplCompleter` lazy-loads Phel functions and caches PHP functions/classes
- Supports dual-context completion: PHP symbols (when input starts with `php/`) and Phel symbols
- `PhelFnLoader` provides hard-coded docs for ~40 native symbols/special forms
- `PhelFnNormalizer` filters private functions and removes duplicates
- `SourceAnalyzer` is a pipeline of `AnalysisStageInterface`; add stages without rewriting the runner
- `ProjectIndexer` is stateless: v1 re-indexes from scratch; hook for file-hash caching lives at the `SymbolExtractor` call-site
- JSON-RPC daemon uses newline-delimited JSON framing for stdio (one request per line, one response per line); transport framing is isolated in `JsonRpcFraming`
- Semantic analysis never re-implements compiler phases: all lex/parse/read/analyze calls go through `CompilerFacade`
