# Api Module

REPL autocompletion, function introspection, documentation, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point).

## Public API (Facade)

- `replComplete(string)` / `replCompleteWithTypes(string)`: REPL autocompletion, plain or with type info (nREPL)
- `getPhelFunctions(list<string> = [])`: all public Phel functions, optionally filtered by namespace
- `analyzeSource(string, string): list<Diagnostic>`: parse and analyze, return diagnostics
- `findSymbolMetadata(string, string = 'user')`: symbol lookup in registry and static catalog
- `indexProject`, `resolveSymbol`, `findReferences`, `completeAtPoint`: project-level symbol tooling (`ProjectIndex`, `Definition`, `Location`, `Completion` transfers)
- PHP interop tooling: `completeAtPoint` returns interop completions when the cursor is in a `php/`-interop position (else Phel completions); `phpInteropHoverAt`/`phpInteropSignatureAt` return reflected hover markdown / LSP signature help. Backed by `PhpInteropReflector` (reflection + composer classmap), `PhpInteropContextResolver` (lexical receiver-type resolution from `:tag`/inline `php/new`/binding, `(:use ...)`/`(use ...)` import aliases incl. `:as`, multi-line forms via `CursorText::before` which trims to the enclosing form), `PhpInteropCompleter`, and `PhpInteropDocResolver`. All degrade to empty/null on unknown types or reflection failure
- `createApiDaemon()`: long-running JSON-RPC daemon

## Dependencies

Run (namespace resolution, directory listing), Compiler (lex, parse, read, analyze phases). `ApiConfig.allNamespaces()` lists the 24 documented namespaces; `githubRef()` returns latest version.

## Key Constraints

- `SourceAnalyzer` runs a pipeline of `list<AnalysisStageInterface>` (`Application/Analysis/`); add/remove stages in `ApiFactory::createSourceAnalyzer()`
- Analysis routes through `CompilerFacade` phases only
- `Infrastructure/NativeSymbolCatalog`: static doc table for special forms / built-ins with no `.phel` source; special forms (`load`, `in-ns`, `use`) need an entry here to show in `phel doc`. `PhelFnLoader` merges it with runtime metadata
- `ProjectIndexer` re-indexes from scratch; caching hook at `SymbolExtractor` call-site
- `ReplCompleter` lazy-loads Phel functions, caches PHP builtin catalog
- `PhelFnNormalizer` normalizes Phel function metadata with group keys and GitHub ref
