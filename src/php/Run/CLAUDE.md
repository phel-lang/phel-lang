# Run Module

Runtime execution: runs Phel namespaces/files, REPL, evaluation, testing, and all CLI commands.

## Gacela Pattern

- **Facade**: `RunFacade` implements `RunFacadeInterface`
- **Factory**: `RunFactory` extends `AbstractFactory<RunConfig>`
- **Config**: `RunConfig` — REPL history path, startup file path
- **Provider**: `RunProvider` — injects 7 facades: `CommandFacade`, `CompilerFacade`, `FormatterFacade`, `InteropFacade`, `BuildFacade`, `ApiFacade`, `ConsoleFacade`

## Public API (Facade)

**Execution**
- `runNamespace(string $namespace): void` — execute a namespace with dependencies
- `runFile(string $filename): void` — execute a Phel file
- `eval(string $phelCode, CompileOptions): mixed` — evaluate code (may throw)
- `structuredEval(string $phelCode, CompileOptions): EvalResult` — evaluate with error capture (never throws)

**Namespace Resolution**
- `getNamespaceFromFile(string $fileOrPath): NamespaceInformation`
- `getDependenciesForNamespace(array $dirs, array $ns): list<NamespaceInformation>`
- `getDependenciesFromPaths(array $paths): list<NamespaceInformation>`
- `evalFile(NamespaceInformation $info): void`
- `loadPhelNamespaces(?string $replStartupFile): void` — load core + startup namespaces
- `getLoadedNamespaces(): list<NamespaceInformation>`

**Directory & Version**
- `getAllPhelDirectories(): array`
- `getVersion(): string`
- `autoDetectEntryPoint(): ?string` — find `core.phel` or `main.phel`

**Error Handling**
- `writeLocatedException(OutputInterface, CompilerException): void`
- `writeStackTrace(OutputInterface, Throwable): void`

## Dependencies

- **Build** (`BuildFacade`) — namespace extraction, dependency resolution, file evaluation
- **Compiler** (`CompilerFacade`) — compilation, evaluation, environment management
- **Command** (`CommandFacade`) — directories, error formatting
- **Console** (`ConsoleFacade`) — version info
- **Api** (`ApiFacade`) — REPL autocompletion
- **Formatter** (`FormatterFacade`) — declared but currently unused
- **Interop** (`InteropFacade`) — declared but currently unused

## Structure

```
Run/
├── Application/        BundledNamespaces, EntryPointDetector, EvalExecutor, FileRunner, NamespaceLoader, NamespaceRunner, NamespacesLoader, ReplHistoryPathResolver, StructuredEvaluator
├── Domain/
│   ├── Init/           NamespaceNormalizer, ProjectTemplateGenerator
│   ├── Repl/           EvalResult, EvalError, ReplCommandIoInterface, ReplErrorFormatter, ReplFormattedError, ReplHistory, ReplPrompt, startup.phel, Hint/
│   ├── Runner/         NamespaceCollector, NamespaceRunnerInterface
│   ├── Test/           TestCommandOptions, CannotFindAnyTestsException
│   └── StdinReaderInterface
├── Infrastructure/
│   ├── Command/        AgentInstallCommand, DoctorCommand, EvalCommand, InitCommand, NsCommand, ReplCommand, RunCommand, TestCommand
│   ├── Service/        DebugLineTap
│   └── PhpStdinReader
└── Gacela files        RunFacade, RunFactory, RunConfig, RunProvider
```

## Key Constraints

- `EvalResult` uses static constructors: `success()`, `incomplete()`, `failure()` — never throws
- REPL supports environment snapshot/restore on eval failure
- `ReplCommandSystemIo` requires PHP `readline` extension; falls back to `ReplCommandFallbackIo`
- `ReplHistoryPathResolver` returns `<projectRoot>/.phel/repl-history`; transparently migrates a legacy `<projectRoot>/.phel-repl-history` (rename + one-line stderr notice unless `PHEL_QUIET_MIGRATION=1`)
- `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel\core` after REPL boot; updates on every eval/exception
- `ReplPrompt` reads `GlobalEnvironmentSingleton::getNs()` to render the current namespace in the prompt
- `ReplErrorFormatter` renders eval-time `Throwable`s for REPL output: short headline, optional hint, trace with internal compiler/run/build/command frames hidden. `Hint/` holds `ReplHintInterface` implementations (`NotCallableHint`, `ArgumentCountHint`, `UndefinedSymbolHint`); register new hints via `RunFactory::createReplHints`
- `NamespaceRunner` resolves full dependency tree before executing
- `BundledNamespaces` lists every `phel.*` module shipped by Phel/installed Phel packages; `NamespaceLoader` and `NamespaceCollector` use it as eager seeds so fully qualified references (`phel.async/delay`, `phel.json/encode`) resolve without explicit `(:require ...)`. `NamespaceLoader` restores the startup namespace via `GlobalEnvironmentSingleton::setNs` after seeding so the REPL/eval session lands in the expected scope.
- This is the **most connected module** — depends on 7 other modules via Provider
