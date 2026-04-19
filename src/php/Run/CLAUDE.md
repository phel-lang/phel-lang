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
├── Application/        EntryPointDetector, EvalExecutor, FileRunner, NamespaceLoader, NamespaceRunner, NamespacesLoader, StructuredEvaluator
├── Domain/
│   ├── Init/           NamespaceNormalizer, ProjectTemplateGenerator
│   ├── Repl/           EvalResult, EvalError, ReplCommandIoInterface, ReplHistory, ReplPrompt, startup.phel
│   ├── Runner/         NamespaceCollector, NamespaceRunnerInterface
│   └── Test/           TestCommandOptions, CannotFindAnyTestsException
├── Infrastructure/
│   ├── Command/        DoctorCommand, EvalCommand, InitCommand, NsCommand, ReplCommand, RunCommand, TestCommand
│   └── Service/        DebugLineTap
└── Gacela files        RunFacade, RunFactory, RunConfig, RunProvider
```

## Key Constraints

- `EvalResult` uses static constructors: `success()`, `incomplete()`, `failure()` — never throws
- REPL supports environment snapshot/restore on eval failure
- `ReplCommandSystemIo` requires PHP `readline` extension; falls back to `ReplCommandFallbackIo`
- `ReplHistory` registers `*1`/`*2`/`*3`/`*e` in `phel\core` after REPL boot; updates on every eval/exception
- `ReplPrompt` reads `GlobalEnvironmentSingleton::getNs()` to render the current namespace in the prompt
- `NamespaceRunner` resolves full dependency tree before executing
- This is the **most connected module** — depends on 7 other modules via Provider
