---
name: module-compiler
description: 'Compiler module: lexer, parser, reader, analyzer, simplifier and emitter pipeline.'
scope: src/php/Compiler
---

# Compiler Module

Core compilation pipeline: Phel source → tokens → AST → analyzed nodes → PHP code.

## Public API (Facade)

| Category | Methods |
|----------|---------|
| Compilation | `compile`, `compileForCache`, `compileForm` (return `EmitterResult`) |
| Evaluation | `eval(string, CompileOptions)`, `evalForm(mixed, CompileOptions)` |
| Pipeline | `lexString → TokenStream`, `parseNext → ?NodeInterface`, `parseAll → FileNode`, `read → ReaderResult`, `analyze(mixed, NodeEnvironmentInterface) → AbstractNode` |
| Tooling | `readFormsBestEffort(code, source) → Generator` of top-level forms for tooling over a buffer mid-edit (`Application/BestEffortFormReader`). Never throws: parse failure ends the stream, read failure skips the form. `getReturn()` is `true` when something was dropped (#3292) |
| Macros | `macroexpand1`, `macroexpand` |
| Analyzer environment | `emptyNodeEnvironment`, `enableDeprecationWarnings`, `deprecationWarningsEnabled` (config key, env var or flag; reported by `phel config`), `withoutDeprecations` (read a source without reporting its notices), `replayDeprecations` (raise a cache hit's recorded notices) |
| Superseded forms | `rejectSupersededForms(mixed $form)`: for callers that read forms outside `CodeCompiler`/`EvalCompiler` (see the diagnostics rule) |
| Suggestions | `findSimilarNames(typed, candidates)`: "did you mean" ranking (`SymbolSuggestionProvider`), used by Build and Lint's `phel/unresolved-namespace` |
| Environment | `initializeGlobalEnvironment`, `resetGlobalEnvironment`, `isGlobalEnvironmentInitialized`, `getGlobalEnvironment`, `initializeNewGlobalEnvironment`, `setGlobalEnvironment` |
| Namespace state | `getNamespaceEnvironmentData`, `restoreNamespaceEnvironmentData` |
| Debugging | `enableDebugLineTap`, `disableDebugLineTap` |
| Utility | `encodeNs` (via `Munge::encodePhpNs`), `hasBalancedParentheses` |

## Dependencies

- **Filesystem**: `FilesystemFacadeInterface::class`, the module's only Provider entry.
- **Config**: `PhelConfig`, wrapped by `CompilerConfig` (`assertsEnabled()`, `warnDeprecationsEnabled()`, `isIntermediateCacheEnabled()`, `getCacheDir()`).
- **Lang**: the widest edge (~200 files). Every phase after the lexer uses `Phel\Lang` values; the emitter writes their FQNs into generated PHP.
- **Shared**: `Munge`, `Printer`, exceptions, `SourceMap\VLQ`. Shared points back through `Facade/CompilerFacadeInterface`; see "Compiler Back-Edge" in `.agnostic-ai/rules/module-shared.md`.

The source map is split on purpose: the writer (`Domain/Emitter/OutputEmitter/SourceMap/SourceMapGenerator`, `SourceMapState`) is emitter state; the reader (`Shared\SourceMap\SourceMapConsumer`) lives in Shared because Command decodes maps and must not `new` a compiler-internal class.

## Phase Pipeline

Lexer (→ `TokenStream`) → Parser (→ `FileNode`) → Reader (→ `ReaderResult`) → Analyzer (→ `AbstractNode` with `NodeEnvironment`) → Simplifier → Emitter (→ `EmitterResult`).

- `EmitterResult` and `ReaderResult` are public value objects in `Phel\Shared`.
- Lexer `Token` and parse-tree nodes live in `Phel\Shared\Parser\Node`; `ExpressionParserFactory` produces them (sub-parsers in `Domain/Parser/ExpressionParser/`).
- The `#php` reader tag expands vectors to indexed PHP arrays and maps to associative ones; literal keyword map keys become strings at read time.

### Unterminated-form reporting

`Application/Parser` stacks the delimiter of every open form. A closer or EOF reaches `readExpression` only when the innermost open form did not consume it; `Domain/Parser/OpenForm` turns the stacked opener into the message, the `ErrorCode` (`UNTERMINATED_LIST` / `_VECTOR` / `_MAP` / `_TABLE` for `#{}`) and the caret location: the opening delimiter, never where the stream ran out. `ListParser` keeps its own throw for a stream ending without `T_EOF`.

An unclosed `"` never fails to lex: the atom rule swallows it (`Balance` relies on that, see `.agnostic-ai/rules/module-balance.md`). `Parser::parseAtomNode` raises `UNTERMINATED_STRING` on the leading quote.

### Prefix forms

`Domain/Parser/ExpressionParser/PrefixFormReader` requires one semantic form after a quote-like or metadata prefix, retaining intervening trivia and reporting missing forms at the prefix. Shared `QuoteNode` and `MetaNode` preserve prefix gaps in their source code without changing semantic child ordering; cloning and child replacement must retain those gaps for the formatter.

### Reader-result cache

Opt-in, off by default (`CompilerConfig::isIntermediateCacheEnabled()`). Wired only into `createCodeCompilerForCache` (build path), never the REPL.

- `CodeCompiler` persists read results via `Domain/Cache/ReaderResultCacheInterface`: `Infrastructure/Cache/FileSystemReaderResultCache` (gzip'd `serialize` under `<cacheDir>/read-result/`, key `md5(version|optLevel|source)`) or `NullReaderResultCache`.
- A warm hit skips lex/parse/read and replays each form's read-phase gensym delta (`Domain/Cache/CachedReaderResult`) before analysis, so `Symbol::gen()` follows the cold trajectory.
- GOTCHA: replayed forms are deserialized, so map keys must compare by value (`Keyword::equals`/`Symbol::equals`), not identity, or keyword-keyed lookups silently miss.
- Gensym names are process-global; a build mixing fresh compiles with compiled-code-cache hits can renumber them (independent of this cache).

## Key Constraints

- Never bypass a phase; each consumes only the previous phase's output.
- `Application/Analyzer::analyzeMacro` owns the shared 1,000-level expansion limit and original call location; per-form `InvokeSymbol` instances must share that analyzer. Expansion depth and private-access state unwind in `finally`, including failed expansion.
- Analyzer nodes carry `NodeEnvironment` with correct context.
- Emitter handles every node type; missing cases throw, never skip.
- Special forms are registered centrally; no ad-hoc handling in the analyzer loop.
- Source locations propagate through all phases.
- Every special-form analyzer checks argument count before reading an argument (`AssertsFormArityTrait::assertArityAtLeast()`). Otherwise `PersistentListInterface::get()` throws a runtime `[PHEL403] Index out of bounds` with no snippet. `SpecialFormArityTest` walks the `AnalyzePersistentList` registry and fails on anything but an `AnalyzerException` (#3297).
- Type declarations validate munged PHP identifiers and reject reserved PHP type names case-insensitively; interface constants reject `let`, `is` and `namespace` (`ReservedDeclarationName`).
- A reference assignment in return position goes through a temporary: returning it directly triggers `Invalid opcode 62/4/0` in PHP 8.6 RC2's OPcache. Both operands still evaluate once.
- Do NOT rename `GlobalEnvironmentSingleton`: the emitter writes `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` into generated PHP, baked into cached `.phel` files. It is an ABI shim over the same slot as `GlobalEnvironmentManager`.
- Do NOT rename `Phel\Lang\LoadClasspath`: `LoadEmitter` bakes its FQN into generated PHP. It lives in `Lang` because its state is the `*load-classpath*` slot in `Lang\Registry`.

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`; `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) share its slot.

### Duplicate-definition reporting

`definitionLocations` records first definitions, so `DuplicateDefinitionException` carries the redefinition's location, `ErrorCode::DUPLICATE_DEFINITION` and a `first defined at …` note (#3267). It gets a snippet because `CodeCompiler`/`EvalCompiler` wrap every `AbstractLocatedException` from analysis (`AnalyzerException` is `final`).

In build mode a redefinition counts only within one `compileString` (`GlobalEnvironment::enterCompileUnit()`), because builds recompile already-loaded namespaces. A name the registry already held is not claimed, so `declare` stays redefinable (#3649).

`phpClasses` maps, per namespace, each lowercased munged PHP class a `defstruct*`, `defexception*`, `defenum*` or `definterface*` emits to the claiming name; a second name raises `DuplicateDefinitionException::forPhpClass` (#3524).

- Interactive mode keeps the check (the same name may reclaim). Analysis mode (lint, `phel analyze`, LSP) skips it: a re-read buffer is not a redefinition.
- Every `ns` form clears its namespace; `snapshot()`/`restore()` carry it so a failed nREPL eval rolls back its claim.

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `.agnostic-ai/rules/module-shared.md`):

- `encodePhpNs`: backslash form, for PHP `namespace` declarations and class FQNs.
- `encodeRegistryKey`: dot form, for Phel registry lookups.

The analyzer uses dot form internally; emission goes through `encodePhpNs`.

## Adding a special form

1. Analyzer in `Domain/Analyzer/TypeAnalyzer/SpecialForm/`, dispatched from `AnalyzePersistentList`. No ad-hoc handling elsewhere.
2. A node in `Domain/Analyzer/Ast/` and its emitter in `Domain/Emitter/OutputEmitter/NodeEmitter/`.
3. A row in `docs/spec/language-surface.md`. `LanguageSurfaceSpecTest` checks the table against the dispatch.
4. A `.test` fixture per behavior, per `.agnostic-ai/rules/compiler-fixtures.md`.

Tests: `tests/php/Unit/Compiler/` per phase, `tests/php/Integration/` end to end.

## Where the rest lives

Each part loads with its directory. Read it first when your change reaches it from elsewhere.

- `.agnostic-ai/rules/module-compiler-analyzer.md` (`Domain/Analyzer/`): interop shorthand, simplification and inlining, type tags.
- `.agnostic-ai/rules/module-compiler-emitter.md` (`Domain/Emitter/`): definition registration, arity shortcut, multi-arity classes, type-specialized emission, generated-class attributes.
- `.agnostic-ai/rules/module-compiler-diagnostics.md` (`Domain/Diagnostic/`, `Domain/Deprecation/`): every compiler warning and deprecation notice. Its detectors live in the lexer, reader and analyzer, so read it before adding or changing any notice.
