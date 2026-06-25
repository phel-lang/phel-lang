# Run Module

Runtime execution: runs Phel namespaces/files, REPL, evaluation, test runner, and most CLI commands.

## Public API (Facade)

| Group | Methods |
|-------|---------|
| Execution | `runNamespace(string)`, `runFile(string)`, `evalFile(NamespaceInformation)`, `eval(string, CompileOptions): mixed`, `structuredEval(string, CompileOptions): EvalResult` (never throws), `loadPhelNamespaces(?string)` (core + startup file) |
| Namespaces | `getNamespaceFromFile`, `getDependenciesForNamespace` (topologically sorted), `getDependenciesFromPaths` |
| Query | `getAllPhelDirectories`, `getLoadedNamespaces`, `getAllNamespaces` (distinct sorted ns across source/test/vendor; via `ProjectNamespaceLister`; powers `phel run`/`phel test` shell completion), `getVersion`, `autoDetectEntryPoint` (prefers `main.phel`, falls back to `core.phel`) |
| Debugging | `enableDebugLineTap(?string $phelFileFilter, string $logPath)`, `disableDebugLineTap` |
| Parallel test | `createParallelTestOrchestrator()`, `createCpuCountDetector()` |
| Watch test | `runTestWatchLoop(callable $runTests, OutputInterface): int` |
| Coverage | `detectCoverageDriver(): ?CoverageDriver`, `buildCoverageReport(array, string): CoverageReport` |
| Errors | `writeLocatedException`, `writeStackTrace` |
| Doctor | `getModuleHealthChecks()` (surfaced by `phel doctor`) |

## Dependencies

Most-connected module. 5 Provider facades:

| Facade | Used for |
|--------|----------|
| Build | namespace extraction, dependency resolution, file evaluation |
| Compiler | compilation, evaluation, environment |
| Command | directories, error formatting, exception hints |
| Api | REPL autocompletion |
| Filesystem | module health check (`phel doctor`) |

Version comes from `Shared\VersionResolver` directly — Run does **not** depend on Console.

## Structure

- `Infrastructure/Command/`: 10 user-facing Symfony commands (incl. `config` — dumps effective merged config) + 1 hidden `_test-worker` (`TestWorkerCommand`).
- `Application/Test/Coverage/`: `CoverageDriver`, `CoverageAggregator`, `CoverageReport`, `CoverageFile`.
- `Runtime/PhelSourceLoader`: cached-PHP boot entry.

## Key Constraints

- **Optimization level**: `RunConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) injects into `EvalExecutor` (`phel eval`) and `CompileExecutor` (`phel compile`); `phel run`/`phel test` pick it up via Build's `FileEvaluator`. REPL and nREPL always compile at level 0 by design.
- **`structuredEval`**: `StructuredEvaluator` (Application) builds the pure `Phel\Shared\Eval\EvalResult` VO via `success()`/`incomplete()`/`failure()`; never throws; owns snapshot/restore orchestration. The VOs carry no logic and live in `Phel\Shared`.
- **REPL**: supports environment snapshot/restore on eval failure. `ReplCommandSystemIo` requires the PHP `readline` extension; falls back to `ReplCommandFallbackIo`. `ReplHistoryPathResolver` returns `.phel/repl-history`, transparently migrating legacy `.phel-repl-history`. `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after REPL boot.
- **Error hints**: live in `Phel\Shared\Exceptions\Hint\` (pure utilities). Add a hint there AND register it in `CommandFactory::createExceptionHints()`; both REPL (`ReplErrorFormatter` via `CommandFacade::getExceptionHintResolver()`) and CLI error paths pick it up.
- **Bundled namespace lazy loading**: `BundledNamespaces` lists every `phel.*` module. `NamespaceLoader` (REPL/eval/lint/lsp/nrepl/watch startup) eagerly seeds only the startup ns + `phel.core`; others load lazily. It registers `LazyBundledNamespaceResolver` (implements Compiler's `BundledNamespaceResolverInterface`) on the global env; `SymbolResolver` invokes it when a fully qualified `phel.*` ref (`phel.json/encode`) hits an unloaded bundle — loads on demand, then retries (no "not defined"). `(require ...)` already loads via dependency resolution.
- **File dedup**: `NamespaceFileTracker` (process-wide static) dedupes evaluated files across eager startup and lazy loads. `NamespaceLoader::reset()` clears it.
- **Script bundles**: `FileRunner` uses `BundledNamespaceDetector` to seed only bundles referenced via fully qualified form (`phel.json/encode`) or Clojure-compatible requires (`clojure.test` → `phel.test`), avoiding cold-start cost for scripts that don't reach bundled modules.
- **Coverage**: `phel test --coverage[=text|clover]` wraps the serial test eval with the driver, maps raw PHP line coverage to `.phel` via `CommandFacade::getCompiledFileLineMap`, filters to project source dirs. Coverage **forces serial execution** (parallel workers can't merge).
- **Parallel testing**: `ParallelTestOrchestrator` spawns a `phel _test-worker` subprocess pool, one ns per length-prefixed JSON work frame; per-ns output is buffered and flushed in input order. `CpuCountDetector` honours `PHEL_TEST_WORKERS`, falls back to `nproc`/`sysctl`/`/proc/cpuinfo`, caps at 8.
- **Watch testing**: `runTestWatchLoop` (`phel test --watch`) polls project src/test dirs for `.phel`/`phel-config.php` mtime changes every 500ms, re-invoking `$runTests` as a subprocess per change.
- **Completion unaffected by lazy load**: the Api completer builds its catalog from `ApiConfig::allNamespaces()`, not from what the REPL loaded.
