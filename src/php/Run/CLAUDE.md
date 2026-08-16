# Run Module

Runtime execution: runs Phel namespaces/files, REPL, evaluation, test runner, and most CLI commands.

## Public API (Facade)

| Group | Methods |
|-------|---------|
| Execution | `runNamespace(string)`, `runFile(string)`, `evalFile(NamespaceInformation)`, `eval(string, CompileOptions): mixed`, `structuredEval(string, CompileOptions): EvalResult` (never throws), `loadPhelNamespaces(?string)` (core + startup file), `flushCompiledCodeCache()` (index to disk before spawning workers) |
| Namespaces | `getNamespaceFromFile`, `getDependenciesForNamespace` (topologically sorted), `getDependenciesFromPaths` |
| Query | `getAllPhelDirectories`, `getLoadedNamespaces`, `getAllNamespaces` (distinct sorted ns across source/test/vendor; via `ProjectNamespaceLister`; powers `phel run`/`phel test` shell completion), `getVersion`, `autoDetectEntryPoint` (prefers `main.phel`, falls back to `core.phel`) |
| Debugging | `enableDebugLineTap(?string $phelFileFilter, string $logPath)`, `disableDebugLineTap`, `breakpoint(PersistentMapInterface): void` (interactive `(break)` sub-REPL; blocks until resume) |
| Parallel test | `createParallelTestOrchestrator()`, `createCpuCountDetector()` |
| Watch test | `runTestWatchLoop(callable $runTests, OutputInterface): int` |
| Coverage | `detectCoverageDriver(): ?CoverageDriver`, `buildCoverageReport(array, string): CoverageReport` |
| Errors | `writeLocatedException`, `writeStackTrace` |
| Doctor | `getModuleHealthChecks()` (surfaced by `phel doctor`) |

## Dependencies

Most-connected module. 5 provider facade contracts:

| Provider key | Used for |
|--------------|----------|
| `BuildFacadeInterface::class` | namespace extraction, dependency resolution, file evaluation |
| `CompilerFacadeInterface::class` | compilation, evaluation, environment |
| `CommandFacadeInterface::class` | directories, error formatting, exception hints |
| `ApiFacadeInterface::class` | REPL autocompletion |
| `FilesystemFacadeInterface::class` | module health check (`phel doctor`) |

Version comes from `Shared\VersionResolver` directly — Run does **not** depend on Console.

Two non-facade edges complete the picture:

| Edge | Where | Why |
|------|-------|-----|
| **Config** | `PhelConfig` / `PhelBuildConfig` read by `RunConfig` and the REPL/test commands | plain data model, no facade exists |
| **Phel** | `Infrastructure/Command/RunCommand.php` calls `Phel::setupRuntimeArgs()` | publishes the entry point's `$argv` before handing over control |

The `Phel` import closes the `Phel <-> Run` cycle: the composition root wires `RunFacade`, and `RunCommand` calls back into the root. It is accepted because the root is where process-wide argv belongs, and it is deliberately one file wide on each side — `ModuleDependencyCycleTest` fails if a second Run file imports the root, and `CompositionRootBoundaryTest` fails if `RunCommand` starts calling more of it than `setupRuntimeArgs()`.

## Structure

- `Infrastructure/Command/`: 11 user-facing Symfony commands (incl. `config` — dumps effective merged config, and `bench` — runs `defbench` benchmarks) + 1 hidden `_test-worker` (`TestWorkerCommand`).
- `Application/Test/Coverage/`: `CoverageDriver`, `CoverageAggregator`, `CoverageReport`, `CoverageFile`, `HtmlCoverageRenderer`.
- `Runtime/PhelSourceLoader`: cached-PHP boot entry.

## Key Constraints

- **Optimization level**: `RunConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) injects into `EvalExecutor` (`phel eval`) and `CompileExecutor` (`phel compile`); `phel run`/`phel test` pick it up via Build's `FileEvaluator`. REPL and nREPL always compile at level 0 by design.
- **`structuredEval`**: `StructuredEvaluator` (Application) builds the pure `Phel\Shared\Eval\EvalResult` VO via `success()`/`incomplete()`/`failure()`; never throws; owns snapshot/restore orchestration. The VOs carry no logic and live in `Phel\Shared`.
- **REPL**: supports environment snapshot/restore on eval failure. `ReplCommandSystemIo` requires the PHP `readline` extension; falls back to `ReplCommandFallbackIo`. `ReplHistoryPathResolver` returns `.phel/repl-history`. `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after REPL boot. `ReplCommand::registerDefaultTap()` adds `phel.repl/print-tap` as a default tap after boot (skipped when a custom startup ns does not define it).
- **Error hints**: live in `Phel\Shared\Exceptions\Hint\` (pure utilities). Add a hint there AND register it in `CommandFactory::createExceptionHints()`; both REPL (`ReplErrorFormatter` via `CommandFacade::getExceptionHintResolver()`) and CLI error paths pick it up.
- **Bundled namespace lazy loading**: `BundledNamespaces` lists every `phel.*` module. `NamespaceLoader` (REPL/eval/lint/lsp/nrepl/watch startup) eagerly seeds only the startup ns + `phel.core`; others load lazily. It registers `LazyBundledNamespaceResolver` (implements Compiler's `BundledNamespaceResolverInterface`) on the global env; `SymbolResolver` invokes it when a fully qualified `phel.*` ref (`phel.json/encode`) hits an unloaded bundle — loads on demand, then retries (no "not defined"). `(require ...)` already loads via dependency resolution.
- **File dedup**: `NamespaceFileTracker` (process-wide static) dedupes evaluated files across eager startup and lazy loads. `NamespaceLoader::reset()` clears it.
- **Script bundles**: `FileRunner` uses `BundledNamespaceDetector` to seed only bundles referenced via fully qualified form (`phel.json/encode`) or Clojure-compatible requires (`clojure.test` → `phel.test`), avoiding cold-start cost for scripts that don't reach bundled modules.
- **Benchmarks**: `BenchCommand` (`phel bench`) mirrors `TestCommand`'s shape — resolve paths through `getDependenciesFromPaths()`, `evalFile()` each namespace (skipping `tests/php/`), then `eval()` a generated `(phel.bench/run-benchmarks {...} 'ns ...)` form. The measurement lives in `phel.bench`, in Phel, so no language boundary sits inside the measured region. `run-benchmarks` returns a boolean verdict which becomes the exit code; a `--tolerance` regression is the only way it returns false. String options are `json_encode`d with `JSON_UNESCAPED_SLASHES` — without it, every `/` of a POSIX path reaches the reader as `\/`.
- **Coverage**: `phel test --coverage[=text|clover|html]` wraps the serial test eval with the driver, maps raw PHP line coverage to `.phel` via `CommandFacade::getCompiledFileLineMap`, filters to project source dirs. Coverage **forces serial execution** (parallel workers can't merge). `html` writes a self-contained static report (`HtmlCoverageRenderer`) to `var/coverage/` by default; override the directory with `html:<dir>` or `--coverage-output`.
- **Parallel testing**: `ParallelTestOrchestrator` spawns a `phel _test-worker` subprocess pool, one ns per length-prefixed JSON work frame; per-ns output is buffered and flushed in input order. `CpuCountDetector` honours `PHEL_TEST_WORKERS`, falls back to `nproc`/`sysctl`/`/proc/cpuinfo`, caps at 8. The parent evaluates only `SharedNamespaces` (what another namespace of the run requires) before dispatching, so a cold cache is warmed once for anything two workers could compile at the same time; every frame carries its `LoadOrderResolver` list (requires + every bundled `phel.*`, dependencies first) and the worker evaluates each file once and never walks dependencies or calls `loadPhelNamespaces()` itself (#3203). A `CompilerException` in a worker is a failed namespace, not a retried worker error.
- **Watch testing**: `runTestWatchLoop` (`phel test --watch`) polls project src/test dirs for `.phel`/`phel-config.php` mtime changes every 500ms, re-invoking `$runTests` as a subprocess per change.
- **Completion unaffected by lazy load**: the Api completer builds its catalog from `ApiConfig::allNamespaces()`, not from what the REPL loaded.
