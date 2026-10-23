---
name: module-api
description: 'Api module: REPL autocompletion, function introspection/docs, and user-code semantic analysis (diagnostics, project index, jump-to-def, find-references, completion at point, PHP-interop tooling).'
scope: src/php/Api
---

# Api Module

## Internal tooling API (Facade)

| Method | Purpose |
|--------|---------|
| `replComplete(string)` / `replCompleteWithTypes(string)` | REPL completion, plain or typed (nREPL) |
| `getPhelFunctions(list<string> = [])` | Public Phel functions, optionally by ns |
| `analyzeSource(string source, string uri): list<Diagnostic>` | Parse + analyze, return diagnostics |
| `findSymbolMetadata(string, string currentNs = 'user'): ?PhelFunction` | Registry + static catalog lookup |
| `completionDoc(string candidate, string currentNs = 'user'): ?string` | Candidate doc markdown |
| `extractDefinitions(string source, string uri)` | One source's definitions |
| `indexProject(list<string> srcDirs): ProjectIndex` | Project symbol index |
| `resolveSymbol(ProjectIndex, ns, symbol): ?Definition` | Jump-to-def |
| `resolveLocalBinding(source, uri, line, col, word): ?Location` | Local to its binding site; `null` if no modelled binder |
| `findReferences(ProjectIndex, ns, symbol)` | Find-references |
| `completeAtPoint(source, line, col, ProjectIndex)` | Interop completions in a `php/` position, else Phel |
| `phpInteropHoverAt(source, line, col): ?string` | Interop hover markdown |
| `phpInteropSignatureAt(source, line, col): ?array` | Interop signature help |
| `phelSignatureAt(source, line, col, currentNs = 'user'): ?array` | Phel call signature help (`PhelSignatureResolver`) |
| `createApiDaemon(): ApiDaemon` | JSON-RPC daemon |

All but `createApiDaemon` are on `Phel\Shared\Facade\ApiFacadeInterface`, injected by `Lint`, `Lsp`, `Nrepl`, `Run`, `Watch`. `createApiDaemon` stays off: `ApiDaemon` is an `Infrastructure` stdio adapter used only by `ApiDaemonCommand`.

Contract transfers are in `Phel\Shared\Api`: `ProjectIndex`, `Definition`, `Location`, `Completion`, `Diagnostic`, `PhelFunction`, `CompletionResultTransfer`. `Transfer/` holds only interop reflection types (`PhpInteropCall`, `PhpInteropClass`, `PhpInteropContext`, `PhpInteropSignature`), which never cross the facade.

The facade is internal, outside the public PHP semver surface (ADR 0021).

## Dependencies

- Run (namespace resolution, directory listing): `RunFacadeInterface::class`.
- Compiler (lex, parse, read, analyze): `CompilerFacadeInterface::class`.
- `ApiConfig::allNamespaces()`: the 27 documented namespaces; `ApiConfig::githubRef()` returns `VersionFinder::LATEST_VERSION`.

`Api <-> Run` is the only mutual Gacela provider pair, a wiring cycle only: both sides use `Phel\Shared\Facade\*Interface`. Providers (here, `Run`, `Lint`, `Lsp`, `Nrepl`, `Watch`) name the concrete `ApiFacade` in method bodies because Gacela's locator resolves by class; every provider key, factory getter and collaborator types `ApiFacadeInterface`. `ModuleDependencyCycleTest` pins `ApiProvider`/`RunProvider` as the only two files.

## PHP Interop Tooling (`Application/Php*`)

Everything degrades to empty/null on unknown types or reflection failure.

| Class | Role |
|-------|------|
| `PhpInteropReflector` | Reflection + composer classmap. `methodSignatureInfo`/`functionSignatureInfo` → `PhpInteropSignature`; `instanceMemberInfo`/`staticMemberInfo` → property/constant/enum-case hover, static properties with the sigil (`$prop`, ADR 0013); `classInfo` → `PhpInteropClass`; `methodReturnType` walks chains; `isInstantiable` guards `php/new` help (interfaces/abstract/enums); `classNames` includes interfaces; `staticMembers` labels enum cases, static properties sigil-first; `globalVariables` reads `PhpSymbolCatalog::SUPERGLOBALS` |
| `PhpInteropContextResolver` | Lexical receiver type from `:tag`, a construction (`(new Foo`, `(Foo.`, `(php/new \Foo` from macro output) or static factory (`(Foo/make`, `(php/:: Foo make`, typed by reflected or tentative return type), `(:use ...)`/`(use ...)` aliases incl. `:as`, multi-line via `CursorText::before`, multi-hop `php/->` chains, indirect `let` rebinds. A binding counts only before the cursor and while its form is still open there, nearest first. A capitalised bare name with no alias or binding is a global class; `Foo.Bar` reads as `Foo\Bar`. Covers `php/*` and `C/member` (static), `(.m recv` / `(.-f recv` (instance). Dot shorthands' receiver *follows* the cursor: `CursorText::firstTokenAfter` (a form's head when one opens there). Match `php/$NAME` (superglobals) *before* `php/<fn>`: `$` is not a word character, so the function branch never sees it |
| `PhpFormTokenizer` | Top-level token splitter shared by scanner + resolver |
| `PhpInteropCallScanner` | Enclosing call + `activeParameter`; handles chained `(php/-> x (a) (b ⟂` |
| `PhpInteropCompleter`, `PhpInteropDocResolver` | Completion, docs |

## Key Constraints

- `Shared\Api\Diagnostic::fromLocatedException()` builds every analyzer diagnostic (`errorCode`, `suggestions`, catalog `fix`), so `phel analyze` and `phel lint` agree (#3464); 1-based columns. `phel analyze` takes files or directories, reports each file by absolute path, exits 1 on an error diagnostic and 2 on a missing path.
- `SourceAnalyzer` stages (`Application/Analysis/`, `AnalysisStageInterface`): Preload → LexAndParse → ReadAndAnalyze, wired in `ApiFactory::createSourceAnalyzer()`.
- `ReadAndAnalyzeStage` wraps its pass in `GlobalEnvironment::enterAnalysisMode()`/`leaveAnalysisMode()`. `PreloadDependenciesStage` really evaluates `phel.*` and the file's dependencies, so the namespace is usually bound; without the guard every top-level `def` raises `DuplicateDefinitionException` and kills the run. A new stage re-analyzing loaded sources needs it too.
- The pass never evaluates a `def`, so `ReadAndAnalyzeStage` skips a form whose macro expansion failed only for that reason, rather than report a `PHEL005` `phel run` would not raise (#3621). `InvokeSymbol` marks them with `UnevaluatedDefinitionException`: a macro whose fn was analysed but never run (not `(def ^:macro m 1)`, which is reported), or a PHP `Error` from a macro whose arguments name a definition absent from the runtime registry (`phel.router/compiled-router` running the file's own `wrap`). Any other failure (`(defstruct 1 [x])`) is reported.
- `GlobalEnvironment` records which pass defined each analysis-only name. Entering a namespace (`setNs` in analysis mode) drops that namespace's macros from earlier unevaluated passes, so the LSP and api-daemon stop expanding a deleted `defmacro`; other namespaces keep theirs. Other `def`s stay, so a `load`ed file linted alone resolves its loader's `defn`s.
- Analysis routes through `CompilerFacade` phases only; never bypass.
- Signatures come from the docstring first, `arglists` metadata second. `DocstringSignatureParser` reads the fenced ```phel arity block `defn` puts atop the docstring; a bare `def` over an `fn` has none, so `PhelFnNormalizer` falls back to `ArglistSignatureParser` over the analyzer-written `arglists` (#3012). Without it, `def`-over-`fn` symbols (`defn`, `defmacro`, `first`, `meta`, ...) lose their signature.
- `Infrastructure/NativeSymbolCatalog`: static docs for special forms / built-ins with no `.phel` source; `PhelFnLoader` merges it with runtime metadata. Special forms (`load`, `in-ns`, `use`) need an entry to show in `phel doc`. Its `signatures` **override** runtime ones and go stale: omit `signatures` when the symbol has a runtime definition.
- `phel doc <x>` is a similarity search (`DocCommand`, `similar_text` >= 45%). No match is not an error: `text` prints "No function matches", `json` gives `[]`, both exit `SUCCESS` (`DocCommandTest`).
- `ProjectIndexer` re-indexes from scratch; the caching hook is the `SymbolExtractor` call site.
- `PhpSymbolCatalog` is the single home for `php/` symbol sets: functions/classes discovered at runtime and memoised; `SUPERGLOBALS` (name → description) is shared by `ReplCompleter` (REPL/nREPL) and `PhpInteropReflector` (LSP) so they cannot drift. `ReplCompleter` lazy-loads Phel functions.
- `PhelFnNormalizer` adds group keys + GitHub ref to function metadata.
- A namespace shown to a user goes through `Domain/RegistryNamespace::sourceName()`. The registry stores `phel.http-client` as `phel.http_client` with no source spelling, so only `phel.*` is decoded; `my_app` and `my-app` are different namespaces to `require` (#3645).
