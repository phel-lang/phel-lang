# Run Module

Runtime execution: runs Phel namespaces/files, REPL, evaluation, testing, and most CLI commands.

## Public API (Facade)

- Execution: `runNamespace(string)`, `runFile(string)`, `evalFile(NamespaceInformation)`, `eval(string, CompileOptions)`, `structuredEval(string, CompileOptions): EvalResult` (success/incomplete/failure), `loadPhelNamespaces(?string)` (core + startup)
- Namespaces: `getNamespaceFromFile`, `getDependenciesForNamespace` (topologically sorted), `getDependenciesFromPaths`
- Query: `getAllPhelDirectories`, `getLoadedNamespaces`, `getVersion`, `autoDetectEntryPoint` (prefers `main.phel`, falls back to `core.phel`)
- Debugging: `enableDebugLineTap(?string $phelFileFilter, string $logPath)`, `disableDebugLineTap`
- Parallel testing: `createParallelTestOrchestrator()` (process pool spawning `phel _test-worker` subprocesses, one ns per length-prefixed JSON work frame, per-ns output buffered and flushed in input order), `createCpuCountDetector()` (honours `PHEL_TEST_WORKERS`, falls back to `nproc`/`sysctl`/`/proc/cpuinfo`, caps at 8)
- Error handling: `writeLocatedException`, `writeStackTrace`

## Dependencies

Most connected module, 5 Provider facades: Build (namespace extraction, dependency resolution, file evaluation), Compiler (compilation, evaluation, environment), Command (directories, error formatting), Api (REPL autocompletion), Filesystem (module health check, surfaced by `phel doctor`). Version comes from `Shared\VersionResolver` directly, so Run does not depend on Console.

## Structure Notes

- `Infrastructure/Command/`: 10 Symfony commands (incl. `config` — dumps effective merged config), one hidden `_test-worker`
- `Runtime/PhelSourceLoader`: cached-PHP boot entry

## Key Constraints

- `RunConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) is injected into `EvalExecutor` (`phel eval`) and `CompileExecutor` (`phel compile`); `phel run`/`phel test` pick the level up via Build's `FileEvaluator`. The REPL and nREPL always compile at level 0 by design
- `StructuredEvaluator` (Application) produces the pure `Phel\Shared\Eval\EvalResult` VO via `success()`/`incomplete()`/`failure()`; it never throws and owns the snapshot/restore orchestration (the VOs carry no logic and live in `Phel\Shared`)
- REPL supports environment snapshot/restore on eval failure
- `ReplCommandSystemIo` requires PHP `readline` extension; falls back to `ReplCommandFallbackIo`
- `ReplHistoryPathResolver` returns `.phel/repl-history`; transparently migrates legacy `.phel-repl-history`
- `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after REPL boot
- `ReplErrorFormatter` renders eval-time `Throwable`s with short headline, hints, and filtered trace
- New `ReplHint` implementations register via `RunFactory::createReplHints()`
- `BundledNamespaces` lists every `phel.*` module; `NamespaceLoader` (REPL startup) uses it as eager seeds. `FileRunner` instead uses `BundledNamespaceDetector` to seed only bundles referenced via fully qualified form (`phel.json/encode`) or matching Clojure-compatible requires (`clojure.test` -> `phel.test`) in the script, avoiding cold-start penalty for scripts that don't reach into bundled modules
