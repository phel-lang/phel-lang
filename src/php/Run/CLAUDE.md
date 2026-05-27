# Run Module

Runtime execution: runs Phel namespaces/files, REPL, evaluation, testing, and all CLI commands.

## Gacela Pattern

- **Facade**: `RunFacade` implements `RunFacadeInterface`
- **Factory**: `RunFactory` extends `AbstractFactory<RunConfig>`
- **Config**: `RunConfig` : REPL history path, startup file path
- **Provider**: `RunProvider` : injects 5 facades: `CommandFacade`, `CompilerFacade`, `BuildFacade`, `ApiFacade`, `ConsoleFacade`

## Public API (Facade)

**Execution**
- `runNamespace(string): void` : execute a namespace with dependencies
- `runFile(string): void` : execute a Phel file
- `getNamespaceFromFile(string): NamespaceInformation`
- `getDependenciesForNamespace(array, array): array` : topologically sorted
- `getDependenciesFromPaths(array): array`
- `evalFile(NamespaceInformation): void`
- `eval(string, CompileOptions = new CompileOptions()): mixed` : compile + execute a Phel snippet
- `structuredEval(string, CompileOptions = new CompileOptions()): EvalResult` : eval returning success/incomplete/failure result
- `loadPhelNamespaces(?string = null): void` : load core + startup

**Query**
- `getAllPhelDirectories(): array`
- `getLoadedNamespaces(): array`
- `getVersion(): string`
- `autoDetectEntryPoint(): ?string` : find `core.phel` or `main.phel`

**Debugging**
- `enableDebugLineTap(): void`
- `disableDebugLineTap(): void`

**Parallel testing**
- `createParallelTestOrchestrator(): ParallelTestOrchestrator` : process-pool runner that spawns `phel _test-worker` subprocesses, dispatches one ns per work frame, buffers per-ns output, flushes in input order
- `createCpuCountDetector(): CpuCountDetector` : honours `PHEL_TEST_WORKERS` env var, falls back to `nproc` / `sysctl` / `/proc/cpuinfo`, caps at 8

**Error Handling**
- `writeLocatedException(OutputInterface, CompilerException): void`
- `writeStackTrace(OutputInterface, Throwable): void`

## Dependencies

- **Build** (`BuildFacade`) : namespace extraction, dependency resolution, file evaluation
- **Compiler** (`CompilerFacade`) : compilation, evaluation, environment management
- **Command** (`CommandFacade`) : directories, error formatting
- **Console** (`ConsoleFacade`) : version info
- **Api** (`ApiFacade`) : REPL autocompletion

## Structure

```
Run/
├── Application/        runners, loaders, REPL infrastructure, Agent/ install helpers, Test/ (parallel runner)
├── Domain/             Agent/, Init/, Repl/ (+ Hint/), Runner/, Test/, stdin/loader interfaces
├── Infrastructure/     Command/ (9 Symfony commands; one hidden `_test-worker`), PhpStdinReader
├── Runtime/            PhelSourceLoader (cached-PHP boot entry)
└── Gacela files        RunFacade, RunFactory, RunConfig, RunProvider
```

`Application/Test/`: `ParallelTestOrchestrator` (proc_open pool), `TestWorkerHandle` (one live subprocess), `WorkerFrame` (length-prefixed JSON framing), `CpuCountDetector` (cross-platform CPU autodetect, capped at 8).

## Key Constraints

- `EvalResult` uses static constructors: `success()`, `incomplete()`, `failure()` : never throws
- REPL supports environment snapshot/restore on eval failure
- `ReplCommandSystemIo` requires PHP `readline` extension; falls back to `ReplCommandFallbackIo`
- `ReplHistoryPathResolver` returns `.phel/repl-history`; transparently migrates legacy `.phel-repl-history`
- `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after REPL boot
- `ReplErrorFormatter` renders eval-time `Throwable`s with short headline, hints, and filtered trace
- New `ReplHint` implementations register via `RunFactory::createReplHints()`
- `BundledNamespaces` lists every `phel.*` module; `NamespaceLoader` (REPL startup) uses it as eager seeds. `FileRunner` instead uses `BundledNamespaceDetector` to seed only bundles referenced via fully qualified form (`phel.json/encode`) or matching Clojure-compatible requires (`clojure.test` -> `phel.test`) in the script, avoiding the cold-start penalty for scripts that don't reach into bundled modules
- This is the most connected module: 5 Provider dependencies
