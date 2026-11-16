---
name: module-run
description: 'Load when changing Run: runtime execution, REPL, eval, test runner, bench, coverage and most CLI commands.'
scope: src/php/Run
---

# Run Module

Runtime execution, REPL, eval, test runner, bench, coverage and most CLI commands (`Infrastructure/Command/`).

## Facade notes

Internal CLI plumbing, not public PHP semver surface (ADR 0021).

- `structuredEval()` never throws; `StructuredEvaluator` builds `Shared\Eval\EvalResult` (`success`/`incomplete`/`failure`) and owns snapshot/restore. The VOs are logic-free.
- `loadPhelNamespaces(?string)` loads core plus the startup file. `flushCompiledCodeCache()` runs before spawning workers.
- `getAllNamespaces()` goes through `ProjectNamespaceLister` and feeds `run`/`test` completion. `autoDetectEntryPoint()` prefers `main.phel`, else `core.phel`.
- `breakpoint()` blocks in a `(break)` sub-REPL.
- `selectChangedTests()` throws `GitUnavailableException` outside a repository.
- `beginPerTestCoverage()` / `perTestCoverageByLine()` / `endPerTestCoverage()` serve hosts that run `phel.test` themselves (mutation worker); one `PerTestCoverageSession` collector per process.
- `readEffectiveConfig()` returns config files plus applied env vars and flags.
- `prepareSourceAnalysis()` (`SourceAnalysisPreparer`) refreshes in-memory Build scans and forgets project compiler namespaces before daemon analysis. Persistent caches and namespaces from Phel's own `src/phel` stay loaded; vendor and project providers refresh regardless of name.

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `BuildFacadeInterface::class` | namespaces, dependency resolution, file evaluation |
| `CompilerFacadeInterface::class` | compilation, evaluation, environment |
| `CommandFacadeInterface::class` | directories, error formatting, exception hints |
| `ApiFacadeInterface::class` | REPL autocompletion (closes the `Api <-> Run` cycle) |
| `FilesystemFacadeInterface::class` | `phel doctor` health check |

Run does not depend on Console (version via `Shared\VersionResolver`).

Non-facade edges:
- **Config**: `PhelConfig` / `PhelBuildConfig` read directly.
- **Phel**: `Infrastructure/Command/RunCommand.php` calls `Phel::setupRuntimeArgs()`, the accepted `Phel <-> Run` cycle kept one file wide. `ModuleDependencyCycleTest` fails if a second Run file imports the root; `CompositionRootBoundaryTest` fails if `RunCommand` calls more than `setupRuntimeArgs()`.

## Commands

- `DoctorCommand` reads the effective config once. The installation check (`Domain/Doctor/InstallationReport`) only warns: install mode, version, project autoloader, Phel libraries in `vendor/`, a vendored Phel under a PHAR, PHP packages the PHAR bundles at another version (compared by commit reference when both sides have one, since `dev-main` names many commits). Composer mode is detected by layout (`<vendor-dir>/phel-lang/phel-lang` beside `<vendor-dir>/composer/`), so a renamed vendor dir still counts. The storage check reports `<phel-dir>/opcache` size and subtrees a retired system id made unreadable; no other command reports it.
- `ExplainCommand` renders one `Shared\Exceptions\ErrorCodeExplanation` or `LintRuleExplainerInterface::explainRule()` result, text or `--format=json`. The console injects `LintFacade::ruleExplainer()` because Lint depends on Run. `ErrorCodeCatalog` and `LintRuleCatalog` own prose and input normalisation and are also rendered in `docs/errors/`: change wording there, never here. Fix-text `docs/` paths go through `InstallDocsPath` (`vendor/phel-lang/phel-lang/docs/...` in user projects). An unknown code or `--format` exits 2.
- `--stack-trace`: `StackTraceOption` declares it once for `run`, `eval`, `repl`, `test`; one filter, `TextExceptionPrinter::getUserFacingTraceString()`.

## REPL and eval

- REPL snapshots and restores the env on eval failure. `ReplCommandSystemIo` needs `readline`, else `ReplCommandFallbackIo`. `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after boot. `ReplCommand::registerDefaultTap()` adds `phel.repl/print-tap`, skipped when a custom startup ns does not define it.
- Uncaught runtime errors: `ReplOutputTrait::writeReplError()` prints `CommandFacade::getRuntimeErrorReport()`, the same report as `phel run`, and logs the full trace. Add no other formatter. Compile-time failures keep their own shapes.
- Error hints: add to `Shared\Exceptions\Hint\` AND register in `CommandFactory::createExceptionHints()`.
- Optimization level: `RunConfig::getOptimizationLevel()` feeds `EvalExecutor` and `CompileExecutor`; `run`/`test` get it via Build's `FileEvaluator`. REPL and nREPL always compile at level 0.

## Bundled namespace loading

- `NamespaceLoader` (REPL, eval, lint, lsp, nrepl, watch) eagerly seeds only the startup ns and `phel.core`. It registers `LazyBundledNamespaceResolver` on the global env; `SymbolResolver` calls it when a qualified `phel.*` ref hits an unloaded bundle, then retries.
- `NamespaceFileTracker` (process-wide static) dedupes evaluated files across eager and lazy loads; `NamespaceLoader::reset()` clears it.
- `FileRunner`'s `BundledNamespaceDetector` seeds only bundles referenced qualified or via Clojure-compatible requires (`clojure.test` -> `phel.test`).
- Completion uses `ApiConfig::allNamespaces()`, not what the REPL loaded, so lazy loading does not affect it.

## Bench

- `BenchCommand` mirrors `TestCommand`, then `eval()`s a generated `(phel.bench/run-benchmarks ...)` form. Measurement lives in Phel so no language boundary sits in the measured region. Only a `--tolerance` regression makes the exit code fail. `json_encode` string options with `JSON_UNESCAPED_SLASHES`, else every `/` reaches the reader as `\/`.
- `--ab=<ref>` never evaluates Phel in the parent and refuses to run outside the repository root. `AbBenchRunner` runs side A (detached `TemporaryWorktree`) and side B (working tree) as `phel bench --store=<tmp>` children, A then B per pair. `AbComparison`/`AbRow` (Domain, pure) decide the verdict.
- `WorktreeVendor` must not symlink `vendor/` wholesale: Composer derives the project root from the resolved `__DIR__`, so side A would run side B's code. It links each package and copies `vendor/composer/` and `vendor/autoload.php`; a different `composer.lock` gets `composer install --no-dev`. `BenchAbCommandTest` pins this.
- Children drop `PHEL_OPCACHE_REEXEC_DONE` so each side gets its own OPcache file cache.
- Cleanup: a `finally` plus `BenchCommand::handleSignal()` (pcntl only). `TemporaryWorktree::forRef()` fixes the temp path before `add()`. `ChildProcess` tracks every child for `abort()` and writes output to files, never pipes, so a chatty stderr cannot deadlock.

## Test runner

- Coverage maps PHP line coverage to `.phel` via `CommandFacade::getCompiledFileLineMap`, filtered to project source dirs, and forces serial execution.
  - `per-test` installs `PerTestCoverageCollector` as `phel.test/*event-hook*` via `PhelVar::alterRoot` after the namespaces load, so the `def` does not overwrite it.
  - pcov instruments only `pcov.directory` (default `<cwd>/src`), but compiled PHP runs from system temp, so an empty report suggests `php -d pcov.directory=/`. It is PHP_INI_SYSTEM; the process cannot widen it.
- Parallel: `ParallelTestOrchestrator` spawns a `phel _test-worker` pool, one ns per length-prefixed JSON frame; output is flushed per ns in input order. `CpuCountDetector` honours `PHEL_TEST_WORKERS` and caps at 8.
  - `--timeout=<s>`: each frame carries a progress file the worker overwrites on every `:begin-test`; the parent records a non-retryable `WorkerOutcome::TimedOut` with its own JUnit document and continues on a fresh worker.
  - Before dispatch the parent evaluates only `SharedNamespaces` (namespaces another one requires, plus every bundled `phel.*`), warming a cold cache once. Each frame carries its `LoadOrderResolver` list; the worker evaluates each file once and never walks dependencies or calls `loadPhelNamespaces()`.
  - `RetryBudget` retries on a fresh worker only for a worker fault (load error, or died without a verdict), never a `CompilerException` or failing test.
  - `junit-xml`: workers return their document via a temp file; `JunitXmlReport` joins them in namespace order.
  - A non-built-in `--reporter` runs serially, like `tap` (the parent never loads it). If still unresolved, `phel.test` throws `UnknownReporterException` and `TestCommand` exits 2.
- GitHub Actions: with `GITHUB_ACTIONS=true` and no `--reporter`, `TestCommandOptionParser::reporters()` adds the `github` reporter. Serially it writes `$GITHUB_STEP_SUMMARY`; in parallel `TestWorkerCommand` drops the variable and the orchestrator appends totals through `GithubStepSummary`, whose line must match the reporter's word for word.
- `--changed[=<ref>]`: `Shared\Process\GitChangedFiles` (shared with `phel mutate`) lists changed files. `AffectedTestNamespaces` (Domain, scan-based, no compiled cache) walks the reverse `(:require ...)` graph; `TestNamespacePruner::pruneTo()` keeps those plus their dependency closure. Zero affected: notice and exit 0, never a silent full run.
- Focus: `phel.test` narrows to `^:focus` tests; the harness returns `[successful? total focused?]`. `TestCommand` fails a focused run when `CI` is set or `--fail-on-focus` is given. In parallel, focus is per namespace.
- `--watch` (`TestWatchLoop`) polls src/test dirs every 500ms for `.phel` / `phel-config.php` changes and reruns `$runTests` as a subprocess.
