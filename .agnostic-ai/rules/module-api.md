---
name: module-api
description: 'Load when changing src/php/Api: REPL completion, function docs, diagnostics, project index, jump-to-def, find-references, PHP-interop tooling.'
scope: src/php/Api
---

# Api Module

Editor and REPL tooling over the compiler: completion, docs, diagnostics, project index, definitions, references, PHP interop help. Internal, outside public semver (ADR 0021).

## Facade

Every method except `createApiDaemon()` is on `Phel\Shared\Facade\ApiFacadeInterface`, injected by Lint, Lsp, Nrepl, Run and Watch. `ApiDaemon` is an `Infrastructure` stdio adapter used only by `ApiDaemonCommand`, so it stays off the contract. Contract transfers live in `Phel\Shared\Api`; `Transfer/` holds only interop reflection types, which never cross the facade.

## Dependencies

- `RunFacadeInterface`: namespace resolution, directory listing. `CompilerFacadeInterface`: lex, parse, read, analyze. Analysis goes through compiler facade phases only.
- `Api <-> Run` is the only mutual provider pair (`.agnostic-ai/rules/module-map.md`). Providers name the concrete `ApiFacade` in method bodies (Gacela resolves by class); keys, getters and collaborators type `ApiFacadeInterface`. `ModuleDependencyCycleTest` pins `ApiProvider`/`RunProvider` as the only two files.
- `ApiConfig::githubRef()` returns `VersionFinder::LATEST_VERSION`.

## Analysis

- `SourceAnalyzer` stages, wired in `ApiFactory::createSourceAnalyzer()`: Preload, LexAndParse, ReadAndAnalyze.
- `PreloadDependenciesStage` really evaluates `phel.*` and the file's dependencies. `ReadAndAnalyzeStage` therefore wraps its pass in `GlobalEnvironment::enterAnalysisMode()`/`leaveAnalysisMode()`; without it every top-level `def` raises `DuplicateDefinitionException`. A new stage re-analyzing loaded sources needs the guard too.
- The pass never evaluates a `def`. `ReadAndAnalyzeStage` skips a form whose macro expansion failed only for that reason (`UnevaluatedDefinitionException`, raised by `InvokeSymbol`), so it reports no `PHEL005` that `phel run` would not raise (#3621). That covers a macro whose fn was analysed but never run, or a PHP `Error` from a macro whose arguments name a definition absent from the runtime registry. Report every other failure (`(defstruct 1 [x])`, `(def ^:macro m 1)`).
- `GlobalEnvironment` records which pass defined each analysis-only name. `setNs` in analysis mode drops that namespace's macros from earlier passes, so LSP and api-daemon stop expanding a deleted `defmacro`; other `def`s stay, so a `load`ed file linted alone resolves its loader's `defn`s.
- Saved-source daemon requests (`analyzeDaemonSource`) refresh project compiler and scan state; `DaemonSourceAnalyzer` replays the latest unsaved buffers so cross-buffer definitions stay available. A saved secondary file preloads its owning primary; an unsaved secondary buffer skips it so disk signatures do not override buffer signatures.
- `Shared\Api\Diagnostic::fromLocatedException()` builds every analyzer diagnostic (`errorCode`, `suggestions`, catalog `fix`), so `phel analyze` and `phel lint` agree. Columns are 1-based. `phel analyze` reports absolute paths and exits 1 on an error diagnostic, 2 on a missing or unreadable path.
- Literal `load` paths resolve through `Shared\LoadedSourceLocator`, also used by the lint cache.
- `ProjectIndexer` re-indexes from scratch; the caching hook is the `SymbolExtractor` call site.

## Docs and signatures

- Signatures: docstring first (`DocstringSignatureParser` reads the fenced `phel` arity block `defn` writes), then `arglists` metadata (`ArglistSignatureParser`). A bare `def` over an `fn` has no docstring block; without the fallback `defn`, `defmacro`, `first`, `meta` lose their signature.
- `Infrastructure/NativeSymbolCatalog`: docs for special forms and built-ins with no `.phel` source. Special forms (`load`, `in-ns`, `use`) need an entry to show in `phel doc`. Its `signatures` **override** runtime ones and go stale: omit them when the symbol has a runtime definition.
- `phel doc <x>` is a similarity search (`similar_text` >= 45%). No match is not an error: text prints "No function matches", json gives `[]`, both exit 0 (`DocCommandTest`).
- Show a namespace through `Domain/RegistryNamespace::sourceName()`. The registry stores `phel.http-client` as `phel.http_client`, so only `phel.*` is decoded; `my_app` and `my-app` are different namespaces (#3645).

## PHP interop (`Application/Php*`)

- Everything degrades to empty or null on unknown types or reflection failure.
- `PhpSymbolCatalog` is the single home for `php/` symbol sets. `SUPERGLOBALS` is shared by `ReplCompleter` (REPL/nREPL) and `PhpInteropReflector` (LSP).
- `PhpInteropContextResolver`: a `let` binding counts only before the cursor and while its form is still open there, nearest first. A capitalised bare name with no alias or binding is a global class; `Foo.Bar` reads as `Foo\Bar`. Dot-shorthand receivers *follow* the cursor (`CursorText::firstTokenAfter`). Match `php/$NAME` (superglobals) *before* `php/<fn>`: `$` is not a word character.
- `PhpInteropReflector::isInstantiable` guards `new` help (not interfaces, abstract classes or enums). Static properties show sigil-first, `$prop` (ADR 0013).
