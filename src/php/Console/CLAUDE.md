# Console Module

CLI application entry point: bootstraps Symfony Console, registers commands, detects version.

## Gacela Pattern

- **Facade**: `ConsoleFacade` implements `ConsoleFacadeInterface`
- **Factory**: `ConsoleFactory` — creates `ConsoleBootstrap`, `VersionFinder`, `ArgvInputSanitizer`
- **Provider**: `ConsoleProvider` — injects `COMMANDS` (12 CLI commands), `FACADE_FILESYSTEM`, `TAG_COMMIT_HASH`, `CURRENT_COMMIT`

## Public API (Facade)

- `getVersion(): string` — full version (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`)
- `runConsole(): void` — execute the CLI application

## CLI Commands (registered via Provider)

Phel commands: `InitCommand`, `ExportCommand`, `FormatCommand`, `NsCommand`, `ReplCommand`, `EvalCommand`, `RunCommand`, `TestCommand`, `DocCommand`, `BuildCommand`, `CacheClearCommand`, `DoctorCommand`.

Gacela 1.13 commands re-exposed under the `phel` CLI: `CacheWarmCommand`, `DebugContainerCommand`, `DebugDependenciesCommand`, `DebugModulesCommand`, `ListModulesCommand`, `ProfileReportCommand`, `ValidateConfigCommand`.

## Dependencies

- **Filesystem** (`FilesystemFacade`) — cleanup after execution
- **Build** — `BuildCommand`, `CacheClearCommand`
- **Run** — `InitCommand`, `EvalCommand`, `ReplCommand`, `NsCommand`, `RunCommand`, `TestCommand`, `DoctorCommand`
- **Api** — `DocCommand`
- **Formatter** — `FormatCommand`
- **Interop** — `ExportCommand`

## Structure

```
Console/
├── Application/        VersionFinder, ArgvInputSanitizer
├── Infrastructure/     ConsoleBootstrap (extends Symfony Application)
└── Gacela files        ConsoleFacade, ConsoleFactory, ConsoleProvider
```

## Key Constraints

- Default command is `repl` (when no command specified)
- `ConsoleBootstrap` runs with auto-exit disabled
- `ArgvInputSanitizer` normalizes `phel run` arguments, separating script options from command arguments via `--`
- `FilesystemFacade.clearAll()` is called after execution for cleanup
