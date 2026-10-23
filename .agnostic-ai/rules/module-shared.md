---
name: module-shared
description: 'Shared module: leaf contract layer of facade interfaces, value objects and pure utilities.'
scope: src/php/Shared
---

# Shared Module

Leaf contract layer: facade interfaces, constants, cross-module value objects, and pure stateless utilities. No Gacela pattern; no outward dependencies (other modules appear only in interface signatures).

## Facade Interfaces (`Facade/`)

Contracts for Compiler, Build, Run, Command, Console, Filesystem, Formatter, Interop, Api. Modules inject these, never concrete facades. Add one when another module starts consuming it; keep imports minimal.

## Compiler Back-Edge (accepted cycle)

`Facade/CompilerFacadeInterface` imports 9 symbols from `Phel\Compiler\Domain`, which keeps `{Compiler, Config, Filesystem, Lang, Shared}` in one SCC. **This is deliberate** (#2785). It is the only Shared → Compiler edge; `tests/php/Unit/Architecture/SharedCompilerBoundaryTest.php` fails if a second one appears or the symbol set changes.

| Kind | Symbols |
|------|---------|
| Method signatures (4) | `AbstractNode`, `NodeEnvironmentInterface`, `GlobalEnvironmentInterface`, `TokenStream` |
| `@throws` tags only (5) | `AnalyzerException`, `LexerValueException`, `UnexpectedParserException`, `UnfinishedParserException`, `ReaderException` |

### Why it is not broken

An SCC decomposes only when *every* back-edge goes; moving `EmitterResult`/`ReaderResult` or the five exceptions alone leaves it intact. Removing all 9 means moving the analyzer AST into Shared (`AbstractNode` ~554 outside references, `NodeEnvironmentInterface` ~291): Shared would become the compiler.

Both alternatives weighed in #2785 cost more than the cycle:

- Primitives/serialized handles: `analyze()` would return an untyped array, losing PHPStan L9 / Psalm L1 type safety.
- Narrow Shared interfaces for AST/env: consumers match on concrete node types, so the interface either restates the node hierarchy or types nothing. `Shared\Parser\Node\NodeInterface` works only because the parse tree has a narrow contract.

### Why it is benign

It exists only over `use` statements (no load or build-order effect). Shared only *names* compiler types, never instantiates one, so no factory gains a cross-module `new`.

### Internal surface (ADR 0021)

The Api and Run contracts are internal tooling; other facade contracts keep the public embedding policy. Internal Shared classes: `Performance/*`, `SourceMap/SupersededSourceMaps`, `Lint/LintRuleExplainerInterface`, `InvocationError`, `StandardError`, `EnvVar`, `Exceptions/SourceMappedErrorHandler`. Internal methods on public classes: `OptimizationLevel::pin`, `NoColor::followOutput`, `ExistingPaths::reportMissing`, `CpuCountDetector::fromEnv`, `ClassNotFoundHint::javaClassHint`, `FrameworkNamespaces::{clojureTarget,isPhel}`.

## Constants

| Const | Value / note |
|-------|--------------|
| `CompilerConstants::PHEL_CORE_NAMESPACE` | `'phel.core'` |
| `CompilerConstants::DEFAULT_SOURCE` | `'string'`: `lexString` source label; `CompilerFacadeInterface` defaults to it to avoid referencing `Application\Lexer` |
| `BuildConstants::BUILD_MODE` | `'*build-mode*'` |
| `ReplConstants::REPL_MODE` | `'*repl-mode*'`: set by `phel repl` only; carries the `phel.repl` refer injection |
| `ReplConstants::INTERACTIVE_MODE` | `'*interactive-mode*'`: set by `phel repl`, `phel eval` and the nREPL server; disables the duplicate-definition guard |
| `CompileOptions` defaults | `DEFAULT_SOURCE`, `DEFAULT_STARTING_LINE`, `DEFAULT_ENABLE_SOURCE_MAPS`, `DEFAULT_EMIT_ONLY` |

## Exceptions (`Exceptions/`)

- `CompilerException`: wraps `AbstractLocatedException` with `CodeSnippet`. `FileException` (file/dir ops); `CompiledCodeIsMalformedException` (PHP `eval()` parse errors).
- `AbstractLocatedException`: base for located errors; carries `SourceLocation` + `ErrorCode`, an optional pre-formatted `relatedLocationNote` that `TextExceptionPrinter` renders under the snippet (`first defined at foo.phel:2`), and `getSuggestions()`: the "did you mean" names (set by `AnalyzerException::cannotResolveSymbol`) so tools need not parse the message.
- `ErrorCode`: enum, five ranges (PHEL0xx analyzer, 1xx parser, 2xx reader, 3xx lexer, 4xx runtime). A case with no production caller fails `ErrorCodeInventoryTest`.
- `SourceMappedErrorHandler` (`@internal`): installed by `bin/phel` through `CommandFacade::reportWarningsAtPhelSource()`. Prints a PHP warning, notice or deprecation from compiled code at the `.phel` file and line its mapper returns; hands unmapped ones back to PHP. Lives here so the compiler's `ErrorNotice` can tell it from a userland handler.
- `RuntimeErrorCodeResolver`: maps an uncaught PHP throwable to its PHEL4xx code, unwrapping one `getPrevious()` level. Only engine classes are recognised; a user's `ex-info` stays uncoded.
- `ErrorCodeCatalog` + `ErrorCodeExplanation`: prose per code; single source for `phel explain` and `composer error-docs:update` (`docs/errors/`). `find()` accepts `PHEL008`, `phel008`, `8`.
- `MissingNsFormException`: thrown by Build's namespace extractor when a file's first form is not `(ns ...)` and analysis hit an unresolved symbol. The analyzer error stays in the previous slot, so the report headline is unchanged; only the hint differs.
- `Exceptions/Hint/`: pure error→hint mappers (`ExceptionHintInterface` + `MissingNsFormHint`, `UndefinedSymbolHint`, `ArgumentCountHint`, `NotCallableHint`, `ClassNotFoundHint`) and `ExceptionHintResolver` (first applicable hint, unwrapping one `getPrevious()` level). Shared so embedding hosts get the same hints.
- `ExceptionPrinterInterface`: implemented by `Command\Application\TextExceptionPrinter`; lives here so `CommandFacadeInterface` does not back-reference `Command\Domain`. Methods: `getExceptionString()`, `getStackTraceString()`, `getUserFacingTraceString($e, $showInternalFrames)` (return strings; the caller picks the output) and side-effecting `printStackTrace()`. Do not re-add `printError()` (bypassed the Symfony `OutputInterface`) or `printException()` (duplicated `CommandExceptionWriter`). `printStackTrace()` looks unused in PHP but `src/phel/test.phel` calls it via interop.

## Value Objects

All `final readonly` and logic-free unless noted. Most live here so a facade contract need not name a foreign module's class (a cycle).

- `EmitterResult`, `ReaderResult`, `BuildOptions`: public compile/read outcomes and build options for PHP hosts (#3521).
- `NamespaceInformation`: `file`, `namespace`, `dependencies`, `isPrimaryDefinition`; produced by Build, consumed by Build/Run/Interop.
- `Eval/`: `EvalResult` (`success()`/`incomplete()`/`failure()`), `EvalError`, `StackFrame`. Returned by `RunFacadeInterface::structuredEval()`, consumed by Nrepl/Watch. Orchestration lives in `Run\Application\StructuredEvaluator`.
- `CompiledFile`: `sourceFile`, `targetFile`, `namespace`, `cached`; returned by `BuildFacadeInterface`.
- `Interop/Wrapper`: relative path + compiled PHP; returned by `InteropFacadeInterface::generateExportCode()`.
- `Api/`: named by `ApiFacadeInterface`, consumed by Api/Lint/Lsp/Nrepl/Run/Watch: `PhelFunction`, `CompletionResultTransfer`, `Diagnostic`, `Definition`, `Location`, `Completion`, `ProjectIndex`. Only logic: `toArray()` and `ProjectIndex`'s pure queries.
- `Formatter/FormatResult`: `changedPaths`, `failedPaths` + emptiness predicates; returned by `FormatterFacadeInterface::format()`. Keeping the lists separate lets `phel format` exit non-zero on an unparsable file.

## Parser Model

- `Parser/ReadModel/CodeSnippet`: `SourceLocation` (start/end) + source string.
- `Parser/Node/*`: parse-tree VOs (`FileNode`, `ListNode`, `SymbolNode`, `MetaNode`, trivia nodes, …) plus the lexer `Token`. AST contract for Formatter, Lint, Api; produced by Compiler.
- Base classes: `AbstractAtomNode` (`Boolean`, `Keyword`, `Nil`, `Number`, `String`, `Symbol`) and `AbstractTriviaNode` (`Whitespace`, `Newline`, `Comma`, `Comment`). The trivia constructor is `final`, so a trivia subclass carries no state; `createWithToken()` returns `static`.

## Printer (`Printer/`)

Stateless; see `.agnostic-ai/rules/module-shared-printer.md`.

## Utility Classes (pure, stateless; instantiate directly)

"Shared so X and Y cannot drift" below means: do not give either consumer its own copy.

| Class | Public API / purpose |
|-------|----------------------|
| `Munge` | `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`; static `canonicalNs()`, `displayNs()` |
| `ColorStyle` | ANSI colors; static `withStyles()`, `noStyles()`; `green/yellow/blue/red/color()` |
| `ByteSize` | static `format(int)`; one size format for `phel build --report` and `phel doctor` |
| `CurrentUser` | static `id()`: memoized effective uid (`posix_geteuid()`, else a temp probe file's owner; null on Windows) for the per-user temp dir check |
| `ScalarCoercion` | config `mixed`→scalar with default: static `toString()`, `toInt()`, `toFloat()`, `toStringList()` |
| `OptimizationLevel` | `resolve()` the configured level (Build and Run config) unless `PHEL_OPTIMIZATION_LEVEL` is set (documented user override, read through `EnvVar::integer()`); `pin()` sets it for subprocesses; `phel mutate` pins 0 |
| `FrameworkNamespaces` | static `matches()` (`phel.*` or `clojure.*`), `isPhel()`, `clojureTarget()` (`clojure.X` -> `phel.X`, `clojure.set` -> `phel.core`, null outside `clojure.*`), two prefix consts. Shared so Build's dependency walk and `ns` check, the analyzer alias remap and lint `phel/unresolved-namespace` cannot drift |
| `ExistingPaths` | static `filter()` drops paths that are neither file nor dir. `reportMissing(paths, output)` names each missing typed path on stderr; the command then returns `Command::INVALID` (2), so a typed path never narrows silently |
| `NumericOption` | `@internal`. static `wholeNumber(input, name, min)` / `number(...)` throw `InvalidArgumentException` naming the option; reported via `InvocationError`. Used by `test`, `bench`, `profile`, `watch`, `nrepl` |
| `InvocationError` | static `report(output, message): int`: stderr + `Command::INVALID` (2). Every "could not run as asked" message goes through it, so stdout keeps only command output (#3525) |
| `EnvVar` | the one reader of Phel env switches: `flag(name)` (`1/true/yes/on`, `0/false/no/off`), `integer(name, min)`, null when unset/empty, throwing with the name otherwise; `isOn(name)` for foreign variables (`CI`), never throws. `ConsoleBootstrap::doRun()` checks Phel switches up front, `bin/phel` the OPcache ones, both exit 2 |
| `StandardError` | `of(output)`: the console's stderr; `redirectEcho(fn)` sends `fn`'s echo to stderr. In machine modes (`--format=json`, `analyze`, `api-daemon`, TAP, JUnit XML) stdout carries only machine output |
| `ResourceUsageFormatter` | `resourceUsageSinceStartOfRequest()`: elapsed time + memory line |
| `PhelProjectDirectory` | `.phel/` dir; static `ensure()`/`path()`/`opcachePath()`/`resolve()`/`resolveCacheDir()`. Order: `PHEL_DIR` → `withPhelDir()` → `<projectRoot>/.phel`. `resolveCacheDir()` (`PHEL_CACHE_DIR` → `resolve()`) is the single source for `BuildConfig::getCacheDir()` and `CompilerConfig::getCacheDir()`, so Compiler needs no reference to Build. `opcachePath()` deliberately skips `withPhelDir()`: `bin/phel` resolves the OPcache file cache before config loads, and `cache:clear` must target the same path |
| `VersionFinder` | pure version string from explicit git inputs (no I/O); `getVersion()`. `LATEST_VERSION` is bumped by `tools/release.sh` and is only the last release tag |
| `VersionResolver` | feeds `VersionFinder` from git, Composer `InstalledVersions`, `.phel-release.php`/`OFFICIAL_RELEASE`. Static `current()` caches the running version per process, beta commit suffixes included; build stamps, runtime guards and compiled-code cache identity use it. Static `currentReference()` caches the own-checkout commit (empty when unavailable) for comparing locked dev dependencies. Instance `resolve()` takes an explicit Phel root. |
| `CompiledSourceHash` | static `of(code, optLevel, envFingerprint)` → cache key: `xxh128` of the source, mixing `\|O{level}` when level>0 and `\|E{fingerprint}` with `cache-env-vars`. Changing the algorithm needs an `INDEX_FORMAT_VERSION` bump. Shared so Build's `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader) key identically |
| `CompileOptions` | source maps, emit-only, optimization levels, `emitAsExpression` (analyse top-level forms in expression context so a folded pure value is not dropped; used by `phel compile`) |
| `PhpAttributeRenderer` | renders `^{:php/attr …}` into PHP 8 attribute lines (`#[\ORM\Column(length: 255)]`). Accepts a keyword, a spec vector, or a vector of specs. Used by `DefStructEmitter`/`DefInterfaceEmitter` and the Interop export generator |
| `Binding/IterationHead` | static `isIterationForm()` / `entries(formName, head)`: bound names of a `for`/`dofor`/`foreach` head, each with the head forms where a reference counts as a use. Not `let`-style pairs: a pairwise read wrongly yields the collection expression as a name. Shared so Lint's `unused-binding`/`shadowed-binding` and Api's `PointCompleter` cannot drift |
| `TagResolver` | `:tag` meta → PHP type string: static `fromMeta()` (null-safe), `normalizeScalar()` (Symbol→name, else non-empty string or null; expands `TYPE_ALIASES` to rooted classes, roots dotted names). `expandImports($tag, $imports)` rewrites `:use` aliases to rooted classes; only the compiler's `TagCanonicalizer` calls it, so tags on the AST and in definition meta are already canonical and readers need no imports. Single source for analyzer type inference, `MethodEmitter`, `PhpAttributeEmitterTrait` |
| `DeprecationResolver` | static `reasonFromMeta()`: the `:deprecated` reason, `'deprecated'` for bare `^:deprecated`, `''` for neither. Shared by Api's `SymbolExtractor` (`Definition::$deprecated`) and Lint's `discouraged-var`. The compiler's `DeprecatedDefinitionWarner` deliberately keeps its own reading: it warns on any truthy value |
| `Performance/OpcacheAdvisor` | pure `advise(...)` (caller passes ini flags) → `OpcacheAdvice`; warns when OPcache won't persist the cache across CLI runs |
| `Performance/OpcacheWorkerFlags` | pure `forFileCache(loaded, dir)` → `-d opcache.enable_cli=1 -d opcache.file_cache=<dir> -d opcache.jit=disable` or `[]`; used by test workers (`RunFactory`) and the CLI re-exec |
| `Performance/OpcacheReexec` | pure `decide(...)` → `OpcacheReexecDecision` (`shouldReexec`, `flags`): whether `bin/phel` should `pcntl_exec` itself with a file cache; the exec stays in `bin/phel`. `withUserIniFlags()` puts user flags **before** Phel's, so PHP's last-wins `-d` keeps `opcache.enable_cli` / `file_cache` / `file_cache_only` authoritative |
| `Performance/OpcacheFileCache` | reads a file cache dir: `entries()`, `detectSystemId(compiledFile)` (probes the `.bin` of an already compiled file), `foreignEntries(id)`, `sizeInBytes()`. Layout `<file_cache>/<32-char system id><absolute source path>.bin`, so a PHAR source yields a `<id>phar:` entry |
| `Performance/OpcacheFileCachePruner` | `pruneForeignSystemIds()`, `pruneOrphanedBins()`, `clearContents()` (empties without removing: PHP aborts when `opcache.file_cache` is missing). Symlinked dirs are unlinked, never descended |
| `Performance/ProcessCommandLine` | `current()` → OS-reported argv (`/proc/self/cmdline`, else `ps -ww -o args=`), or `[]`. Needed because PHP strips interpreter flags from `$_SERVER['argv']` |
| `Performance/UserIniFlags` | pure `extract(processArgs, scriptArgv)` → the user's `-d`/`-n`/`-c` flags, or `[]` when the two argv views are ambiguous. Lets the re-exec keep ini overrides |
| `Process/CpuCountDetector` | worker count: `PHEL_TEST_WORKERS` (`fromEnv()`, via `EnvVar`), else `nproc` / `sysctl` / `/proc/cpuinfo`, else 4; `detect()` caps at 8, `detectMax()` does not. Used by `phel test --parallel` and `phel mutate --parallel` |
| `Process/GitChangedFiles` | `changedFiles(?ref, projectDir)`: absolute changed paths (uncommitted + untracked; else since the default branch's merge base; or `git diff <ref>`); throws `GitUnavailableException` outside a repo. Used by `--changed` in `test` and `mutate` |
| `Process/WorkerFrame` | pure length-prefixed JSON codec (`<8 hex digits>\n<payload>`) over worker stdin/stdout: `encode()`, `readBlocking()`, `decodeBody()`, `headerSize()`. Spoken by `_test-worker` (Run) and `_mutate-worker` (Mutate) |
| `Process/PhelBinaryLocator` | static `locate()` → the running `phel` entry point (`SCRIPT_FILENAME`, else `argv[0]`, else repo `bin/phel`), so workers run the parent's code and config. Shared because `RunFactory` and `MutateFactory` may not reach into each other |

## SourceMap (`SourceMap/`)

| Class | Purpose / producer → consumer |
|-------|-------------------------------|
| `VLQ` | pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); used by Compiler's `SourceMapGenerator` and `SourceMapConsumer` |
| `SourceMapConsumer` | decodes `mappings` into `getOriginalLine()` / `getMappedLines()`. Here because Compiler (`EvaluatedCodeException`) and Command (`FilePositionExtractor`) both decode; the writer (`SourceMapGenerator`, `SourceMapState`) stays in Compiler |
| `SourceMapSiblings` | naming for `<file>.php.map` + `<file>.phel`. Written by Build (`FileCompiler`, `SecondaryFileHarvester`), read by Command (`SourceMapExtractor`) |
| `BuiltFilePreamble` | `<?php declare(strict_types=1);` + building-version comment; `prepend()` (`FileCompiler`), `codeStartLine()` (`SourceMapExtractor`) |
| `InlineSourceMapComments` | `// ` / `// ;;` prefixes for inline maps in eval'd code. Written by `Shared\EmitterResult`, parsed by `SourceMapExtractor` + `EvaluatedCodeException` |
| `SupersededSourceMaps` | per-process headers of compiled files this process `require`d before they were overwritten. Written by Build (`CompiledCodeCache::put`), read first by Command (`SourceMapExtractor`), so an earlier code frame maps through its own map |

## Key Constraints

- Leaf dependency: contracts + utilities only, never business logic.
- Utilities stay stateless, safe to instantiate without module context.
- The one permitted outward edge is `CompilerFacadeInterface → Compiler\Domain` (see "Compiler Back-Edge"). A second Shared → Compiler import, or a new compiler type in that contract, breaks `SharedCompilerBoundaryTest`; widen it only deliberately, and update the rationale.
