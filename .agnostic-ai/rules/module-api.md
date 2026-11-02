---
name: module-api
description: 'Load when changing src/php/Api: REPL completion, function docs, diagnostics, project index, jump-to-def, find-references, PHP-interop tooling.'
scope: src/php/Api
---

# Api Module

## Facade (internal, outside public semver, ADR 0021)

| Method | Purpose |
|--------|---------|
| `replComplete(string)` / `replCompleteWithTypes(string)` | REPL completion, plain or typed (nREPL) |
| `getPhelFunctions(list<string> = [])` | Public Phel functions, optionally by ns |
| `analyzeSource(string source, string uri): list<Diagnostic>` | Parse + analyze |
| `findSymbolMetadata(string, string currentNs = 'user'): ?PhelFunction` | Registry + static catalog lookup |
| `completionDoc(string candidate, string currentNs = 'user'): ?string` | Doc markdown |
| `extractDefinitions(string source, string uri)` | One source's definitions |
| `indexProject(list<string> srcDirs): ProjectIndex` | Project symbol index |
| `resolveSymbol(ProjectIndex, ns, symbol): ?Definition` | Jump-to-def |
| `resolveLocalBinding(source, uri, line, col, word): ?Location` | Local to its binder; `null` if none modelled |
| `findReferences(ProjectIndex, ns, symbol)` | Find-references |
| `completeAtPoint(source, line, col, ProjectIndex)` | Interop completions in a `php/` position, else Phel |
| `phpInteropHoverAt(source, line, col): ?string` | Interop hover |
| `phpInteropSignatureAt(source, line, col): ?array` | Interop signature help |
| `phelSignatureAt(source, line, col, currentNs = 'user'): ?array` | Phel signature help (`PhelSignatureResolver`) |
| `createApiDaemon(): ApiDaemon` | JSON-RPC daemon |

- All but `createApiDaemon` are on `Phel\Shared\Facade\ApiFacadeInterface`, injected by `Lint`, `Lsp`, `Nrepl`, `Run`, `Watch`. `ApiDaemon` is an `Infrastructure` stdio adapter used only by `ApiDaemonCommand`, so it stays off.
- Contract transfers are in `Phel\Shared\Api`: `ProjectIndex`, `Definition`, `Location`, `Completion`, `Diagnostic`, `PhelFunction`, `CompletionResultTransfer`. `Transfer/` holds only interop reflection types (`PhpInteropCall`, `PhpInteropClass`, `PhpInteropContext`, `PhpInteropSignature`); they never cross the facade.

## Dependencies

- `RunFacadeInterface::class`: namespace resolution, directory listing.
- `CompilerFacadeInterface::class`: lex, parse, read, analyze.
- `ApiConfig::allNamespaces()`: the 27 documented namespaces. `ApiConfig::githubRef()` returns `VersionFinder::LATEST_VERSION`.
- `Api <-> Run` is the only mutual Gacela provider pair (wiring only). Both sides use `Phel\Shared\Facade\*Interface`. Providers (`Api`, `Run`, `Lint`, `Lsp`, `Nrepl`, `Watch`) name the concrete `ApiFacade` in method bodies (Gacela resolves by class); every provider key, factory getter and collaborator types `ApiFacadeInterface`. `ModuleDependencyCycleTest` pins `ApiProvider`/`RunProvider` as the only two files.

## PHP interop tooling (`Application/Php*`)

Everything degrades to empty/null on unknown types or reflection failure.

| Class | Role |
|-------|------|
| `PhpInteropReflector` | Reflection + composer classmap. `methodSignatureInfo`/`functionSignatureInfo` return `PhpInteropSignature`; `instanceMemberInfo`/`staticMemberInfo` give property/constant/enum-case hover (static properties with sigil `$prop`, ADR 0013); `classInfo` returns `PhpInteropClass`; `methodReturnType` walks chains; `isInstantiable` guards `php/new` help (not interfaces/abstract/enums); `classNames` includes interfaces; `staticMembers` labels enum cases, static properties sigil-first; `globalVariables` reads `PhpSymbolCatalog::SUPERGLOBALS` |
| `PhpInteropContextResolver` | Receiver type from `:tag`; a construction (`(new Foo`, `(Foo.`, `(php/new \Foo`); a static factory (`(Foo/make`, `(php/:: Foo make`, by reflected or tentative return type); `(:use ...)`/`(use ...)` aliases incl. `:as`; multi-line via `CursorText::before`; multi-hop `php/->` chains; indirect `let` rebinds. A binding counts only before the cursor and while its form is open there, nearest first. A capitalised bare name with no alias or binding is a global class; `Foo.Bar` reads as `Foo\Bar`. Covers `php/*`, `C/member` (static), `(.m recv` / `(.-f recv` (instance). Dot-shorthand receivers *follow* the cursor: `CursorText::firstTokenAfter`. Match `php/$NAME` (superglobals) *before* `php/<fn>`: `$` is not a word character |
| `PhpFormTokenizer` | Top-level token splitter for scanner + resolver |
| `PhpInteropCallScanner` | Enclosing call + `activeParameter`; handles chained `(php/-> x (a) (b ⟂` |
| `PhpInteropCompleter`, `PhpInteropDocResolver` | Completion, docs |

## Key Constraints

- `Shared\Api\Diagnostic::fromLocatedException()` builds every analyzer diagnostic (`errorCode`, `suggestions`, catalog `fix`), so `phel analyze` and `phel lint` agree (#3464). Columns are 1-based. `phel analyze` takes files or directories, reports absolute paths, exits 1 on an error diagnostic, 2 on a missing path.
- `SourceAnalyzer` stages (`Application/Analysis/`, `AnalysisStageInterface`): Preload, LexAndParse, ReadAndAnalyze, wired in `ApiFactory::createSourceAnalyzer()`.
- `ReadAndAnalyzeStage` wraps its pass in `GlobalEnvironment::enterAnalysisMode()`/`leaveAnalysisMode()`. `PreloadDependenciesStage` really evaluates `phel.*` and the file's dependencies, so without the guard every top-level `def` raises `DuplicateDefinitionException` and kills the run. A new stage re-analyzing loaded sources needs it too.
- The pass never evaluates a `def`. `ReadAndAnalyzeStage` skips a form whose macro expansion failed only for that reason, so no `PHEL005` that `phel run` would not raise (#3621). `InvokeSymbol` marks them with `UnevaluatedDefinitionException`: a macro whose fn was analysed but never run (not `(def ^:macro m 1)`, which is reported), or a PHP `Error` from a macro whose arguments name a definition absent from the runtime registry (`phel.router/compiled-router` running the file's own `wrap`). Report any other failure (`(defstruct 1 [x])`).
- `GlobalEnvironment` records which pass defined each analysis-only name. `setNs` in analysis mode drops that namespace's macros from earlier unevaluated passes (LSP and api-daemon stop expanding a deleted `defmacro`); other namespaces keep theirs. Other `def`s stay, so a `load`ed file linted alone resolves its loader's `defn`s.
- Analysis goes through `CompilerFacade` phases only; never bypass.
- Signatures: docstring first, `arglists` metadata second. `DocstringSignatureParser` reads the fenced ```phel arity block `defn` puts atop the docstring. A bare `def` over an `fn` has none, so `PhelFnNormalizer` falls back to `ArglistSignatureParser` over the analyzer-written `arglists` (#3012); without it `defn`, `defmacro`, `first`, `meta` etc. lose their signature.
- `Infrastructure/NativeSymbolCatalog`: static docs for special forms / built-ins with no `.phel` source, merged with runtime metadata by `PhelFnLoader`. Special forms (`load`, `in-ns`, `use`) need an entry to show in `phel doc`. Its `signatures` **override** runtime ones and go stale: omit them when the symbol has a runtime definition.
- `phel doc <x>` is a similarity search (`DocCommand`, `similar_text` >= 45%). No match is not an error: `text` prints "No function matches", `json` gives `[]`, both exit `SUCCESS` (`DocCommandTest`).
- `ProjectIndexer` re-indexes from scratch; the caching hook is the `SymbolExtractor` call site.
- `PhpSymbolCatalog` is the single home for `php/` symbol sets (functions/classes discovered at runtime, memoised). `SUPERGLOBALS` (name to description) is shared by `ReplCompleter` (REPL/nREPL) and `PhpInteropReflector` (LSP). `ReplCompleter` lazy-loads Phel functions.
- `PhelFnNormalizer` adds group keys + GitHub ref to function metadata.
- Show a namespace through `Domain/RegistryNamespace::sourceName()`. The registry stores `phel.http-client` as `phel.http_client`, so only `phel.*` is decoded; `my_app` and `my-app` are different namespaces to `require` (#3645).
