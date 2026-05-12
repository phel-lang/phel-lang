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
- `evalFile(string): void`
- `loadPhelNamespaces(?string = null): void` : load core + startup

**Query**
- `getAllPhelDirectories(): array`
- `getLoadedNamespaces(): array`
- `getVersion(): string`
- `autoDetectEntryPoint(): ?string` : find `core.phel` or `main.phel`

**Debugging**
- `enableDebugLineTap(): void`
- `disableDebugLineTap(): void`

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
├── Application/        BundledNamespaces, EntryPointDetector, EvalExecutor, FileRunner, NamespaceLoader, NamespaceRunner, NamespacesLoader, ReplHistoryPathResolver, StructuredEvaluator
├── Domain/
│   ├── Init/           NamespaceNormalizer, ProjectTemplateGenerator
│   ├── Repl/           EvalResult, EvalError, ReplCommandIoInterface, ReplErrorFormatter, ReplFormattedError, ReplHistory, ReplPrompt, Hint/ (startup.phel lives at <repo>/resources/repl/)
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

- `EvalResult` uses static constructors: `success()`, `incomplete()`, `failure()` : never throws
- REPL supports environment snapshot/restore on eval failure
- `ReplCommandSystemIo` requires PHP `readline` extension; falls back to `ReplCommandFallbackIo`
- `ReplHistoryPathResolver` returns `.phel/repl-history`; transparently migrates legacy `.phel-repl-history`
- `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel.core` after REPL boot
- `ReplErrorFormatter` renders eval-time `Throwable`s with short headline, hints, and filtered trace
- New `ReplHint` implementations register via `RunFactory::createReplHints()`
- `BundledNamespaces` lists every `phel.*` module; loader uses it as eager seeds so fully qualified refs (`phel.json/encode`) resolve without explicit `(:require ...)`
- This is the most connected module: 5 Provider dependencies
