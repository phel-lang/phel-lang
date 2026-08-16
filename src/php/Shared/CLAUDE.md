# Shared Module

Leaf contract layer: facade interfaces, constants, cross-module value objects, and pure stateless utilities. No Gacela pattern; no outward dependencies (other modules appear only in interface signatures).

## Facade Interfaces (`Facade/`)

Cross-module facade contracts: Compiler, Build, Run, Command, Console, Formatter, Interop, Api. Modules inject these (`*FacadeInterface`), never concrete facades — enables dependency inversion. Add a new interface here when another module starts consuming it; keep its imports minimal.

## Compiler Back-Edge (accepted cycle)

`Facade/CompilerFacadeInterface` imports 11 symbols from `Phel\Compiler\Domain`, which keeps `{Compiler, Config, Filesystem, Lang, Shared}` in one SCC. **This is deliberate** (decided in #2785). It is the only Shared → Compiler edge, and `tests/php/Unit/Architecture/SharedCompilerBoundaryTest.php` fails the build if a second one appears or the symbol set changes.

| Kind | Symbols |
|------|---------|
| Method signatures (6) | `AbstractNode`, `NodeEnvironmentInterface`, `GlobalEnvironmentInterface`, `TokenStream`, `EmitterResult`, `ReaderResult` |
| `@throws` tags only (5) | `AnalyzerException`, `LexerValueException`, `UnexpectedParserException`, `UnfinishedParserException`, `ReaderException` |

### Why it is not broken

An SCC decomposes only when *every* back-edge goes, so partial moves are churn with no structural payoff: relocating just the five exceptions leaves the cycle exactly as it was.

Removing all 11 means moving the analyzer AST into Shared. `AbstractNode` has ~554 references outside Shared and `NodeEnvironmentInterface` ~291. Shared would become the compiler, inverting the leaf-layer rule it exists to enforce.

The two alternatives weighed in #2785 both cost more than the cycle does:

- Primitives/serialized handles at the boundary: `analyze()` would return an untyped array, discarding the type safety PHPStan L9 / Psalm L1 enforce across those references.
- Narrow Shared interfaces for AST/env: analyzer consumers match on concrete node types, so the interface either restates the node hierarchy in Shared or is too generic to type anything. Contrast `Shared\Parser\Node\NodeInterface`, which works precisely because the *parse tree* has a genuinely narrow contract.

### Why it is benign

The cycle is a static-analysis artifact over `use` statements, not a runtime one. No initialization order, autoloading, or build-order problem follows from it, and PHP has no module-level compilation unit.

It also does not weaken the Gacela rule it appears to touch. Shared only *names* compiler types in a signature; it never instantiates one, and no factory gains a cross-module `new`. The dependency inversion this interface exists for (consumers injecting `CompilerFacadeInterface` rather than the concrete `CompilerFacade`) holds regardless of who owns the types in its signature.

## Constants

| Const | Value / note |
|-------|--------------|
| `CompilerConstants::PHEL_CORE_NAMESPACE` | `'phel.core'` |
| `CompilerConstants::DEFAULT_SOURCE` | `'string'` — `lexString` source label; `CompilerFacadeInterface` defaults to it to avoid referencing `Application\Lexer` |
| `BuildConstants::BUILD_MODE` | `'*build-mode*'` |
| `ReplConstants::REPL_MODE` | `'*repl-mode*'` — set by `phel repl` only; carries the `phel.repl` refer injection |
| `ReplConstants::INTERACTIVE_MODE` | `'*interactive-mode*'` — set by `phel repl`, `phel eval` and the nREPL server; stands the duplicate-definition guard down |
| `CompileOptions` defaults | `DEFAULT_SOURCE`, `DEFAULT_STARTING_LINE`, `DEFAULT_ENABLE_SOURCE_MAPS`, `DEFAULT_EMIT_ONLY` |

## Exceptions (`Exceptions/`)

- `CompilerException` — wraps `AbstractLocatedException` with `CodeSnippet`
- `AbstractLocatedException` — base for located errors; carries `SourceLocation` + `ErrorCode` (enum, PHEL001-PHEL310: analyzer, parser, reader, lexer)
- `FileException` (file/dir ops); `CompiledCodeIsMalformedException` (wraps PHP `eval()` parse errors)
- `Exceptions/Hint/` — pure error→hint mappers (`ExceptionHintInterface` + `UndefinedSymbolHint`, `ArgumentCountHint`, `NotCallableHint`) and `ExceptionHintResolver` (first applicable hint, unwrapping one `getPrevious()` level). Shared so both the REPL formatter (Run) and the CLI error writer (Command) surface identical guidance.
- `ExceptionPrinterInterface` — contract for exception/stack-trace rendering; implemented by `Command\Application\TextExceptionPrinter`, consumed by Run's REPL error formatter. Lives here so `CommandFacadeInterface` doesn't back-reference `Command\Domain`. Four methods: `getExceptionString()`, `getStackTraceString()`, `getUserFacingTraceString()` (all string-returning, so the caller decides where the text goes) plus the one side-effecting `printStackTrace()`. The two side-effecting `print*` siblings are gone on purpose: `printError()` echoed straight to stdout, bypassing the Symfony `OutputInterface` that every command already writes through, and `printException()` only wrapped `getExceptionString()` in an error-log write that `CommandExceptionWriter` already performs. `printStackTrace()` looks unused in PHP but is called from `src/phel/test.phel` via interop.

## Value Objects

- `NamespaceInformation` — `final readonly` DTO (`file`, `namespace`, `dependencies`, `isPrimaryDefinition`); produced by Build, consumed across Build/Run/Interop. Lives here so Shared facade contracts don't back-reference a foreign module's `Domain`.
- `Eval/` — `final readonly` VOs for eval outcomes: `EvalResult` (`success()`/`incomplete()`/`failure()` named ctors), `EvalError`, `StackFrame`. Returned by `RunFacadeInterface::structuredEval()`, consumed by Nrepl/Watch. Producing orchestration lives in `Run\Application\StructuredEvaluator`, so these stay logic-free.
- `CompiledFile` — `final readonly` DTO (`sourceFile`, `targetFile`, `namespace`, `cached`); produced by Build's compilers, returned by `BuildFacadeInterface`.
- `Interop/Wrapper` — `final readonly` DTO (relative path + compiled PHP) for generated export wrappers; returned by `InteropFacadeInterface::generateExportCode()`.
- `Api/` — `final readonly` DTOs named by `ApiFacadeInterface`, consumed by Api/Lint/Lsp/Nrepl/Run/Watch: `PhelFunction` and `CompletionResultTransfer` (function metadata, typed REPL completions) plus the semantic-analysis payloads `Diagnostic`, `Definition`, `Location`, `Completion` and `ProjectIndex`. They live here for the usual reason: the contract cannot name a `Phel\Api` class without turning `Api <-> Shared` into a cycle. All are logic-free apart from `toArray()` projections and `ProjectIndex`'s pure queries.
- `Formatter/FormatResult` — `final readonly` DTO (`changedPaths`, `failedPaths` + emptiness predicates); returned by `FormatterFacadeInterface::format()`. Separating the two lists is what lets `phel format` exit non-zero on an unparsable file.

## Parser Model

- `Parser/ReadModel/CodeSnippet` — `SourceLocation` (start/end) + source string; pure data.
- `Parser/Node/*` — parse-tree VOs (`FileNode`, `ListNode`, `SymbolNode`, `MetaNode`, trivia nodes, …) plus the lexer `Token`. De-facto AST contract consumed by Formatter, Lint, Api; Compiler produces them. Living here removes `CompilerFacadeInterface → Compiler\Domain` references.
- Two shape-only base classes keep the leaf VOs one-liners: `AbstractAtomNode` (scalar atoms: `Boolean`, `Keyword`, `Nil`, `Number`, `String`, `Symbol`) and `AbstractTriviaNode` (`Whitespace`, `Newline`, `Comma`, `Comment`). Its constructor is `final`, so a trivia subclass carries no state of its own; `createWithToken()` returns `static`.

## Printer (`Printer/`)

Stateless strategy-pattern printer (see `Printer/CLAUDE.md`); consumers instantiate directly.

## Utility Classes (pure, stateless — instantiate directly)

| Class | Public API / purpose |
|-------|----------------------|
| `Munge` | namespace/symbol encoding: `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`; static `canonicalNs()`, `displayNs()` |
| `ColorStyle` | ANSI colors; static factories `withStyles()`, `noStyles()`; `green/yellow/blue/red/color()` |
| `ScalarCoercion` | coerce config `mixed`→scalar with default: static `toString()`, `toInt()`, `toFloat()`, `toStringList()` |
| `FrameworkNamespaces` | static `matches(string)` → is this the `phel.*`/`clojure.*` space Phel itself provides, plus the two prefix consts. A require of one resolves at runtime even when a source scan cannot see it (precompiled+lazily-loaded stdlib, or a `clojure.*` compat shim with no Phel counterpart at all, like `clojure.set`), so both Build's dependency walk and the emitted `ns` form must tolerate one instead of reporting it missing. Shared so those two cannot drift on which namespaces are exempt |
| `ExistingPaths` | static `filter(list<string>)` → drops paths that are neither a file nor a dir. Shared by the `lint` / `watch` commands and `WatchRunner` so a user-supplied path list narrows identically everywhere |
| `ResourceUsageFormatter` | `resourceUsageSinceStartOfRequest()` → "Time: HH:MM:SS.mmm, Memory: X.XX MB" |
| `PhelProjectDirectory` | manages `.phel/` dir; static `ensure()`/`path()`/`resolve()`/`resolveCacheDir()`. Effective location: `PHEL_DIR` env → `withPhelDir()` override → `<projectRoot>/.phel`. `resolveCacheDir()` (`PHEL_CACHE_DIR` env → `resolve()`) is the single source for `BuildConfig::getCacheDir()` and `CompilerConfig::getCacheDir()`, so the build cache and the intermediate-artifact cache cannot drift apart — and so Compiler needs no reference to Build |
| `VersionFinder` | pure version-string builder from explicit git inputs (no I/O); `getVersion()`. `LATEST_VERSION` const is bumped by `tools/release.sh` |
| `VersionResolver` | gathers ambient version inputs (git working copy, Composer `InstalledVersions`, build-time `.phel-release.php`/`OFFICIAL_RELEASE`) and calls `VersionFinder`; `resolve()`. Console and Run consume directly, so neither owns version-detection wiring |
| `CompiledSourceHash` | static `of(code, optLevel)` → compiled-code cache key (mixes `\|O{level}` when level>0, plain `md5` at 0). Shared so Build's `FileEvaluator` writer and `SecondaryFileHarvester` reader key identically |
| `CompileOptions` | source maps, emit-only mode, optimization levels, `emitAsExpression` (analyse top-level forms in expression context so a folded pure value surfaces instead of being dropped — used by `phel compile`) |
| `PhpAttributeRenderer` | renders `^{:php/attr …}` specs into PHP 8 attribute lines (`#[\ORM\Column(length: 255)]`). Accepts bare keyword (`:ORM/Entity`), single spec vector (`[:ORM/Column {:length 255}]`), or vector of specs. Consumed by `DefStructEmitter`/`DefInterfaceEmitter` + Interop export generator |
| `Binding/IterationHead` | static `isIterationForm()` / `entries(formName, head)` — the bound names of a `for`/`dofor`/`foreach` head, each with the head forms where a reference counts as a use. Neither head is a `let`-style pair list, so a pairwise read offers the collection expression as a bound name; shared so Lint's `unused-binding` / `shadowed-binding` rules and Api's `PointCompleter` cannot drift on that grammar |
| `TagResolver` | resolves a Phel `:tag` meta value to a scalar PHP type string: static `fromMeta()` (null-safe) + `normalizeScalar()` (Symbol→name, else non-empty string or null). Single source for the analyzer type-inferrers, `MethodEmitter`, and `PhpAttributeEmitterTrait` |
| `Performance/OpcacheAdvisor` | pure `advise(...)` (caller passes ini flags) → `OpcacheAdvice` (`optimal`, `messages`); flags when OPcache won't persist the compiled-code cache across CLI runs |
| `Performance/OpcacheWorkerFlags` | pure `forFileCache(loaded, dir)` → `-d opcache.enable_cli=1 -d opcache.file_cache=<dir>` flag pairs (or `[]`); shared by parallel test workers (`RunFactory`) and the CLI re-exec |
| `Performance/OpcacheReexec` | pure `decide(...)` → `OpcacheReexecDecision` (`shouldReexec`, `flags`); whether `bin/phel` should `pcntl_exec` itself with a persistent file cache. Reuses `OpcacheWorkerFlags`; the actual exec is the thin edge in `bin/phel`. `OpcacheReexecDecision::withUserIniFlags()` splices the user's flags **before** Phel's so PHP's last-wins `-d` order keeps `opcache.enable_cli` / `file_cache` / `file_cache_only` authoritative |
| `Performance/ProcessCommandLine` | `current()` → the OS-reported argv of the running process (`/proc/self/cmdline`, else `ps -ww -o args=`), or `[]` when unknown. Exists because PHP strips interpreter flags from `$_SERVER['argv']` |
| `Process/WorkerFrame` | length-prefixed JSON framing (`<8 hex digits>\n<payload>`) between a parent and a long-lived worker subprocess over stdin/stdout: `encode()`, `readBlocking()`, `decodeBody()`, `headerSize()`. Spoken by `phel test --parallel` (`_test-worker`, Run) and `phel mutate` (`_mutate-worker`, Mutate); pure codec, no process handling |
| `Performance/UserIniFlags` | pure `extract(processArgs, scriptArgv)` → the user's `-d`/`-n`/`-c` flags, or `[]` when the two argv views do not reconstruct unambiguously. Lets the CLI re-exec carry ini overrides into the child instead of dropping them |

## SourceMap (`SourceMap/`)

| Class | Purpose / producer → consumer |
|-------|-------------------------------|
| `VLQ` | pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); used by Compiler's `SourceMapGenerator` and by `SourceMapConsumer` below |
| `SourceMapConsumer` | read side of the codec: decodes a `mappings` string into `getOriginalLine()` / `getMappedLines()`. Lives here, not in the emitter, because both Compiler (`EvaluatedCodeException`) and Command (`FilePositionExtractor`) decode maps while only the emitter writes them. The writer (`SourceMapGenerator`, `SourceMapState`) stays in Compiler |
| `SourceMapSiblings` | naming convention for `<file>.php.map` + `<file>.phel` artifacts. Written by Build (`FileCompiler`, `SecondaryFileHarvester`), read by Command (`SourceMapExtractor`) |
| `BuiltFilePreamble` | fixed `<?php declare(strict_types=1);` line before generated code; `prepend()` (writer: `FileCompiler`), `codeStartLine()` (reader: `SourceMapExtractor`) |
| `InlineSourceMapComments` | `// ` / `// ;;` metadata comment prefixes for inline maps in eval'd code. Written by Compiler's `EmitterResult`, parsed by `SourceMapExtractor` + `EvaluatedCodeException` |

## Key Constraints

- Leaf dependency: contracts + utilities only, never business logic.
- Exceptions are cross-module: thrown and caught everywhere.
- Utilities stay stateless: safe to instantiate without module context.
- The one permitted outward edge is `CompilerFacadeInterface → Compiler\Domain` (see "Compiler Back-Edge" above). Adding a second Shared → Compiler import, or a new compiler type to that contract, breaks `SharedCompilerBoundaryTest`; widen it only deliberately, and update the rationale when you do.
