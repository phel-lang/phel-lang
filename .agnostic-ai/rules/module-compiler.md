---
name: module-compiler
description: 'Load when changing the compiler pipeline: lexer, parser, reader, analyzer, simplifier, emitter, facade or global environment.'
scope: src/php/Compiler
---

# Compiler Module

Pipeline: Lexer (`TokenStream`) → Parser (`FileNode`) → Reader (`ReaderResult`) → Analyzer (`AbstractNode` + `NodeEnvironment`) → Simplifier → Emitter (`EmitterResult`). Each phase consumes only the previous phase's output; never bypass one. `EmitterResult`, `ReaderResult`, `Token` and parse-tree nodes are public values in `Phel\Shared`.

## Facade notes

`CompilerFacadeInterface` lists the API. Non-obvious entries:

- `readFormsBestEffort(code, source)`: top-level forms of a buffer mid-edit (`Application/BestEffortFormReader`). Never throws: a parse failure ends the stream, a read failure skips the form. The generator's `getReturn()` is `true` when something was dropped.
- `rejectSupersededForms(form)`: for callers that read forms outside `CodeCompiler`/`EvalCompiler` (diagnostics rule).
- `withoutDeprecations` reads a source without reporting notices; `replayDeprecations` raises a cache hit's recorded notices.
- `forgetProjectNamespaces(retainedNamespaces)` drops compiler and registry state outside the given stdlib list, plus the eval memo, for saved-source daemon analysis.
- `findSimilarNames` is the one "did you mean" ranking (`SymbolSuggestionProvider`), shared with Build and Lint.

## Dependencies

- Provider: only `FilesystemFacadeInterface`.
- Config: `PhelConfig`, wrapped by `CompilerConfig`.
- Lang: the widest edge. Every phase after the lexer uses `Phel\Lang` values, and the emitter writes their FQNs into generated PHP.
- Shared: `Munge`, `Printer`, exceptions, source maps. Shared points back through `CompilerFacadeInterface` (`.agnostic-ai/rules/module-shared.md`, "Compiler Back-Edge"). The source map writer stays here; the reader lives in Shared (`.agnostic-ai/rules/module-shared-sourcemap.md`).

## Parser

- `Application/Parser` stacks the delimiter of every open form. A closer or EOF reaches `readExpression` only when the innermost form did not consume it. `Domain/Parser/OpenForm` turns the stacked opener into the message, the `UNTERMINATED_*` code (`_TABLE` is `#{}`) and the caret: the opening delimiter, never where the stream ran out. `ListParser` keeps its own throw for a stream ending without `T_EOF`.
- An unclosed `"` never fails to lex: the atom rule swallows it (`Balance` relies on that, `.agnostic-ai/rules/module-balance.md`). `Parser::parseAtomNode` raises `UNTERMINATED_STRING` on the leading quote.
- `Domain/Parser/ExpressionParser/PrefixFormReader` requires one semantic form after a quote-like or metadata prefix and reports a missing one at the prefix. `QuoteNode` and `MetaNode` keep the trivia gap in their source; cloning and child replacement must keep it for the formatter.
- The `#php` reader tag turns vectors into indexed PHP arrays and maps into associative ones; literal keyword map keys become strings at read time.

## Reader-result cache

Opt-in (`CompilerConfig::isIntermediateCacheEnabled()`), wired only into `createCodeCompilerForCache` (build path), never the REPL.

- `Infrastructure/Cache/FileSystemReaderResultCache` stores gzip'd `serialize` under `<cacheDir>/read-result/`, keyed on `md5(format|version|Olevel|source)`. The version is `VersionResolver::current()` with its development reference, so another revision never replays. Bump `FORMAT_VERSION` when the serialized layout or collection hashing changes.
- A warm hit skips lex, parse and read, and replays each form's read-phase gensym delta (`Domain/Cache/CachedReaderResult`) so `Symbol::gen()` follows the cold trajectory.
- Replayed forms are deserialized: map keys must compare by value (`Keyword::equals`, `Symbol::equals`), not identity, or keyword lookups silently miss.
- Gensym names are process-global; a build mixing fresh compiles with compiled-code-cache hits can renumber them, independent of this cache.

## Key constraints

- `Application/Analyzer::analyzeMacro` owns the 1,000-level expansion limit and the original call location; per-form `InvokeSymbol` instances must share that analyzer. Depth and private-access state unwind in `finally`, also on failure.
- The emitter handles every node type; a missing case throws, never skips.
- Register special forms centrally in `AnalyzePersistentList`; no ad hoc handling in the analyzer loop.
- Source locations propagate through all phases.
- Every special-form analyzer checks argument count before reading an argument (`AssertsFormArityTrait::assertArityAtLeast()`). Otherwise `get()` throws a runtime `Index out of bounds` with no snippet. `SpecialFormArityTest` walks the registry and fails on anything but an `AnalyzerException`.
- Type declarations validate munged PHP identifiers and reject reserved PHP type names case-insensitively; interface constants also reject `let`, `is` and `namespace` (`ReservedDeclarationName`).
- A reference assignment in return position goes through a temporary: returning it directly triggers `Invalid opcode 62/4/0` in PHP 8.6 RC2's OPcache. Both operands still evaluate once.
- Do NOT rename `Infrastructure\GlobalEnvironmentSingleton` or `Phel\Lang\LoadClasspath`: generated PHP, including cached files, calls their FQNs. The singleton is an ABI shim over the slot `GlobalEnvironmentManager` uses.

## Global environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`.

- Analysis mode is reentrant. Its outermost scope restores compiler state when `NsSymbol` rejects a namespace, including declarations inside macros or loaded files. Valid passes keep definitions for other files.
- `definitionLocations` records first definitions, so `DuplicateDefinitionException` carries the redefinition's location and a `first defined at` note. It gets a snippet because `CodeCompiler`/`EvalCompiler` wrap every `AbstractLocatedException` from analysis.
- Build mode counts a redefinition only within one `compileString` (`GlobalEnvironment::enterCompileUnit()`), because builds recompile loaded namespaces. A name the registry already held is not claimed, so `declare` stays redefinable.
- `phpClasses` maps, per namespace, each lowercased munged class a `defstruct*`, `defexception*`, `defenum*` or `definterface*` emits to its claiming name; a second name raises `DuplicateDefinitionException::forPhpClass`. Interactive mode keeps the check (the same name may reclaim); analysis mode (lint, `phel analyze`, LSP) skips it. Every `ns` form clears its namespace; `snapshot()`/`restore()` carry it so a failed nREPL eval rolls back its claim.

## Namespace encoding

The analyzer works in dot form (`Munge::encodeRegistryKey`); emission of PHP namespaces and class FQNs goes through `Munge::encodePhpNs`.

## Adding a special form

1. Analyzer in `Domain/Analyzer/TypeAnalyzer/SpecialForm/`, dispatched from `AnalyzePersistentList`.
2. A node in `Domain/Analyzer/Ast/` and its emitter in `Domain/Emitter/OutputEmitter/NodeEmitter/`.
3. A row in `docs/spec/language-surface.md`; `LanguageSurfaceSpecTest` checks it against the dispatch.
4. A `.test` fixture per behavior (`.agnostic-ai/rules/compiler-fixtures.md`).

## Where the rest lives

Each part loads with its directory. Read it first when your change reaches it from elsewhere.

- `.agnostic-ai/rules/module-compiler-analyzer.md`: interop shorthand, symbol resolution, simplification, type tags.
- `.agnostic-ai/rules/module-compiler-emitter.md`: definition registration, arity shortcut, multi-arity classes, specialization, generated classes.
- `.agnostic-ai/rules/module-compiler-diagnostics.md`: every warning and deprecation notice. Its detectors live in the lexer, reader and analyzer.
