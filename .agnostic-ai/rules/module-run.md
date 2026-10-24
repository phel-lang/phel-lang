---
name: module-run
description: 'Run module: runtime execution, REPL, evaluation, test runner and most CLI commands.'
scope: src/php/Run
---

# Run Module

## Internal tooling API (Facade)

Internal CLI plumbing, not public PHP semver surface (ADR 0021).

| Group | Methods |
|-------|---------|
| Execution | `runNamespace(string)`, `runFile(string)`, `evalFile(NamespaceInformation)`, `eval(string, CompileOptions): mixed`, `structuredEval(string, CompileOptions): EvalResult` (never throws), `loadPhelNamespaces(?string)` (core + startup file), `flushCompiledCodeCache()` (before spawning workers) |
| Namespaces | `getNamespaceFromFile`, `getDependenciesForNamespace` (topologically sorted), `getDependenciesFromPaths`, `namespaceExists(string): bool` |
| Query | `getAllPhelDirectories`, `getLoadedNamespaces`, `getAllNamespaces` (via `ProjectNamespaceLister`; feeds `phel run`/`phel test` completion), `getVersion`, `autoDetectEntryPoint` (`main.phel`, else `core.phel`) |
| Debugging | `enableDebugLineTap(?string $phelFileFilter, string $logPath)`, `disableDebugLineTap`, `breakpoint(PersistentMapInterface): void` (blocking `(break)` sub-REPL) |
| Parallel test | `createParallelTestOrchestrator()`, `createCpuCountDetector()` (`Shared\Process\CpuCountDetector`) |
| Bench A/B | `createAbBenchRunner()` (`phel bench --ab`) |
| Changed tests | `selectChangedTests(?string $ref, list<NamespaceInformation>, string $projectDir): ChangeSelection` (throws `GitUnavailableException` outside a repository) |
| Watch test | `runTestWatchLoop(callable $runTests, OutputInterface): int` |
| Coverage | `detectCoverageDriver(): ?CoverageDriver`, `buildCoverageReport(array, string): CoverageReport`, `buildPerTestCoverageReport(array, string): PerTestCoverageReport`; for hosts running `phel.test` themselves (mutation worker): `beginPerTestCoverage(): ?string`, `perTestCoverageByLine(): array`, `endPerTestCoverage()` (`PerTestCoverageSession`, one collector per process) |
| Errors | `writeLocatedException`, `writeStackTrace` |
| Config and doctor | `readEffectiveConfig(): EffectiveConfigResult` (config files plus applied env vars and flags), `getModuleHealthChecks()` (`phel doctor`) |

## Dependencies

Provider facade contracts:

| Provider key | Used for |
|--------------|----------|
| `BuildFacadeInterface::class` | namespaces, dependency resolution, file evaluation |
| `CompilerFacadeInterface::class` | compilation, evaluation, environment |
| `CommandFacadeInterface::class` | directories, error formatting, exception hints |
| `ApiFacadeInterface::class` | REPL autocompletion |
| `FilesystemFacadeInterface::class` | module health check (`phel doctor`) |

Run does **not** depend on Console (version via `Shared\VersionResolver`).

Non-facade edges:
- **Config**: `PhelConfig` / `PhelBuildConfig` read directly (plain data, no facade).
- **Phel**: `Infrastructure/Command/RunCommand.php` calls `Phel::setupRuntimeArgs()` (publishes `$argv`), closing an accepted `Phel <-> Run` cycle kept one file wide: `ModuleDependencyCycleTest` fails if a second Run file imports the root, `CompositionRootBoundaryTest` fails if `RunCommand` calls more of it than `setupRuntimeArgs()`.

## Structure

- `Infrastructure/Command/`: 12 Symfony commands + hidden `_test-worker` (`TestWorkerCommand`).
- `DoctorCommand` reads the effective config once for the configuration and storage checks. The storage check reports `<phel-dir>/opcache` size and subtrees a retired system id made unreadable (no other command reports it).
- `ExplainCommand` (`phel explain PHEL001`, `phel explain phel/unused-require`) renders one `Phel\Shared\Exceptions\ErrorCodeExplanation` or `Shared\Lint\LintRuleExplainerInterface::explainRule()` result, text or `--format=json`. The console injects `LintFacade::ruleExplainer()`: Lint depends on Run, so Run cannot reach Lint. `ErrorCodeCatalog` and `LintRuleCatalog` own prose and input normalisation (case, `PHEL`/`phel/` prefix, leading zeroes), also rendered in `docs/errors/`: change wording there, never here. Fix text `docs/` paths go through `Infrastructure/Command/InstallDocsPath` (`vendor/phel-lang/phel-lang/docs/...` in user projects); diagnostics and `docs/errors/` keep raw text. No argument lists all; an unknown code or `--format` exits 2.
- `Application/Test/Coverage/`: `CoverageDriver`, `CoverageAggregator`, `CoverageReport`, `CoverageFile`, `HtmlCoverageRenderer`, `PerTestCoverageCollector` (the `phel.test/*event-hook*`), `PerTestCoverageReport`.
- `Runtime/PhelSourceLoader`: cached-PHP boot entry.

## Key Constraints

- **Optimization level**: `RunConfig::getOptimizationLevel()` (`PhelConfig::OPTIMIZATION_LEVEL`) feeds `EvalExecutor` and `CompileExecutor`; `phel run`/`phel test` get it via Build's `FileEvaluator`. REPL and nREPL always compile at level 0 by design.
- **`structuredEval`**: `StructuredEvaluator` (Application) builds `Phel\Shared\Eval\EvalResult` via `success()`/`incomplete()`/`failure()`, never throws, and owns snapshot/restore. The VOs are logic-free, in `Phel\Shared`.
- **REPL**: env snapshot/restore on eval failure. `ReplCommandSystemIo` needs `readline`, else `ReplCommandFallbackIo`. `ReplHistoryPathResolver`: `.phel/repl-history`. `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after boot. `ReplCommand::registerDefaultTap()` adds `phel.repl/print-tap` after boot (skipped when a custom startup ns does not define it).
- **`--stack-trace`**: `StackTraceOption` (declared once, read via `isEnabled($input)`) for `run`, `eval`, `repl`, `test`; one filter, `TextExceptionPrinter::getUserFacingTraceString()`.
- **Uncaught runtime errors**: `ReplOutputTrait::writeReplError()` prints `CommandFacade::getRuntimeErrorReport()`, the same report as `phel run`, and logs the full trace. Do not add another formatter here (#3264 removed one). Compile-time failures keep their own shapes (`writeLocatedException`, `CompiledCodeIsMalformedException`).
- **Error hints**: add to `Phel\Shared\Exceptions\Hint\` AND register in `CommandFactory::createExceptionHints()`
- **Bundled namespace lazy loading**: `BundledNamespaces` lists every `phel.*` module. `NamespaceLoader` (REPL/eval/lint/lsp/nrepl/watch) eagerly seeds only the startup ns + `phel.core`. It registers `LazyBundledNamespaceResolver` (Compiler's `BundledNamespaceResolverInterface`) on the global env; `SymbolResolver` calls it when a qualified `phel.*` ref (`phel.json/encode`) hits an unloaded bundle, then retries. `(require ...)` already loads via dependency resolution.
- **File dedup**: `NamespaceFileTracker` (process-wide static) dedupes evaluated files across eager and lazy loads; `NamespaceLoader::reset()` clears it.
- **Script bundles**: `FileRunner`'s `BundledNamespaceDetector` seeds only bundles referenced qualified (`phel.json/encode`) or via Clojure-compatible requires (`clojure.test` → `phel.test`), sparing cold-start cost.
- **Benchmarks**: `BenchCommand` mirrors `TestCommand`: paths via `getDependenciesFromPaths()`, `evalFile()` each namespace (skipping `tests/php/`), then `eval()` a generated `(phel.bench/run-benchmarks {...} 'ns ...)` form. Measurement lives in Phel (`phel.bench`) so no language boundary sits in the measured region. Its boolean is the exit code; only a `--tolerance` regression makes it false. String options are `json_encode`d with `JSON_UNESCAPED_SLASHES`, else every `/` of a POSIX path reaches the reader as `\/`.
- **Bench A/B**: `phel bench --ab=<ref>` never evaluates Phel in the parent; it refuses to run outside the repository root.
  - `AbBenchRunner` runs side A (detached `TemporaryWorktree` in system temp) and side B (working tree) as `phel bench --store=<tmp>` children, A then B per pair. `AbComparison`/`AbRow` (Domain, pure) decide sign agreement, `noise` and the every-pair tolerance verdict.
  - `WorktreeVendor` must not symlink `vendor/` wholesale: Composer derives the project root from `__DIR__`, resolving the link, so side A would silently run side B's code. It links each package and copies `vendor/composer/` + `vendor/autoload.php`; a different `composer.lock` gets `composer install --no-dev`. `BenchAbCommandTest` pins this with a fixture whose committed class differs from its working tree.
  - Children drop `PHEL_OPCACHE_REEXEC_DONE` so each side gets its own OPcache file cache.
  - Cleanup: a `finally` plus `BenchCommand::handleSignal()` (SIGINT/SIGTERM, pcntl only). `TemporaryWorktree::forRef()` fixes the temp path before `add()` runs; `ChildProcess` tracks every child (git, composer too) for `abort()`, and writes child output to files, never pipes, so a chatty stderr cannot deadlock.
- **Coverage**: `phel test --coverage[=text|clover|html]` maps PHP line coverage to `.phel` via `CommandFacade::getCompiledFileLineMap`, filtered to project source dirs. Coverage **forces serial execution** (parallel workers can't merge).
  - `--coverage=per-test` installs `PerTestCoverageCollector` as `phel.test/*event-hook*` via `PhelVar::alterRoot` after the namespaces load (so the `def` does not overwrite it); it toggles the driver on `:begin-test`/`:end-test`. JSON: `tests` (test to file to lines) and `lines` (file to line to tests).
  - `html` (`HtmlCoverageRenderer`) writes to `var/coverage/`, or `html:<dir>` / `--coverage-output`.
  - pcov instruments only `pcov.directory` (default `<cwd>/src`), but compiled PHP runs from the system temp dir, so an empty report suggests `php -d pcov.directory=/` (#3270). It is PHP_INI_SYSTEM; the process cannot widen it.
- **Parallel testing**: `ParallelTestOrchestrator` spawns a `phel _test-worker` pool, one ns per length-prefixed JSON work frame; output is flushed per ns in input order. `Shared\Process\CpuCountDetector` honours `PHEL_TEST_WORKERS`, falls back to `nproc`/`sysctl`/`/proc/cpuinfo`, caps at 8.
  - The parent evaluates only `SharedNamespaces` (namespaces another one in the run requires, plus every bundled `phel.*`) before dispatch, warming a cold cache once for anything two workers could compile concurrently. Each frame carries its `LoadOrderResolver` list (requires + every bundled `phel.*`, dependencies first); the worker evaluates each file once and never walks dependencies or calls `loadPhelNamespaces()`.
  - Result frames carry a `WorkerOutcome`; `RetryBudget` retries a namespace on a fresh worker only for a worker fault (load error thrown, or died without a verdict), never a `CompilerException` or failing test. `Retried:` counts only namespaces a retry rescued.
  - `junit-xml`: workers return their document in the frame (via a temp file); `JunitXmlReport` joins them in namespace order to stdout or `-o`, progress and summary to stderr.
  - A non-built-in `--reporter` runs serially, like `tap` (test code registers it via `register-reporter!`; the parent never loads it). If still unresolved, `phel.test` throws `UnknownReporterException` before any test runs and `TestCommand` exits 2.
- **GitHub Actions**: with `GITHUB_ACTIONS=true` and no `--reporter`, `TestCommandOptionParser::reporters()` adds `phel.test`'s `github` reporter beside the default (`::error` annotations, `::group::` per namespace, `::warning` on a focused run). Serially the reporter writes `$GITHUB_STEP_SUMMARY`; in parallel `TestWorkerCommand` drops the variable and `ParallelTestOrchestrator` appends totals through `GithubStepSummary`, whose line must stay word for word what the reporter writes.
- **Changed tests**: `phel test --changed[=<ref>]` gets changed files from git via `Shared\Process\GitChangedFiles` (shared with `phel mutate --changed`): uncommitted vs `HEAD`, else since the merge base with the default branch, or `git diff <ref>`; untracked count. Each `.phel` maps to a namespace via `getNamespaceFromFile()` (an `(in-ns ...)` secondary of `phel.core` resolves to `phel.core`). `AffectedTestNamespaces` (Domain, pure, scan-based, no dependency on the compiled cache) walks the reverse `(:require ...)` graph to test namespaces; `TestNamespacePruner::pruneTo()` keeps those plus their dependency closure. Zero affected: notice and exit 0, never a silent full run.
- **Focus**: `phel.test` narrows to `^:focus` tests and reports `focused-run?`; the harness form returns `[successful? total focused?]` and worker JSON carries `focused`; `TestCommand` fails a focused run when `CI` is set or `--fail-on-focus` is given (`ParallelRunOutcome` for the pool). In parallel, focus is per namespace.
- **Watch testing**: `runTestWatchLoop` (`phel test --watch`) polls project src/test dirs every 500ms for `.phel`/`phel-config.php` mtime changes, re-running `$runTests` (a subprocess) per change.
- **Completion**: the Api completer uses `ApiConfig::allNamespaces()`, not what the REPL loaded, so lazy loading does not affect it.
