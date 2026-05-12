# Console Module

CLI application entry point: bootstraps Symfony Console, registers commands, detects version.

## Gacela Pattern

- **Facade**: `ConsoleFacade` implements `ConsoleFacadeInterface`
- **Factory**: `ConsoleFactory` : creates `ConsoleBootstrap`, `ArgvInputSanitizer`, `WarnDeprecationsFlag`; reads `VersionFinder` from `Phel\Shared`
- **Provider**: `ConsoleProvider` : injects `COMMANDS` (21 Phel + 7 framework), `FACADE_FILESYSTEM`, `TAG_COMMIT_HASH`, `CURRENT_COMMIT`

## Public API (Facade)

- `getVersion(): string` : full version (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`)
- `runConsole(): void` : execute the CLI application

## CLI Commands

Registered via `ConsoleProvider::COMMANDS` (aggregated from per-module `ConsoleCommandProviderInterface` impls in `Infrastructure/Command/*Commands.php`):

- **Run**: `init`, `agent-install`, `ns`, `repl`, `eval`, `run`, `test`, `doctor`
- **Api**: `doc`, `analyze`, `index`, `api-daemon`
- **Build**: `build`, `cache:clear`
- **Formatter**: `format`
- **Interop**: `export`
- **Lint**: `lint`
- **Lsp**: `lsp`
- **Nrepl**: `nrepl`
- **Profile**: `profile`
- **Watch**: `watch`
- **Gacela framework re-exposed**: `cache:warm`, `debug:container`, `debug:dependencies`, `debug:modules`, `list:modules`, `profile:report`, `validate:config`

## Dependencies

- **Filesystem** (`FilesystemFacade`) : cleanup after execution

Sibling Command classes are wired via per-module `*Commands.php` providers, not via Facade injection.

## Structure

```
Console/
├── Application/        ArgvInputSanitizer, WarnDeprecationsFlag
├── Domain/             ConsoleCommandProviderInterface
├── Infrastructure/
│   ├── ConsoleBootstrap (extends Symfony Application)
│   └── Command/        per-module *Commands providers (Run, Api, Build, Lint, ...)
└── Gacela files        ConsoleFacade, ConsoleFactory, ConsoleProvider
```

## Key Constraints

- Default command is `repl` (when no command specified)
- `ConsoleBootstrap` runs with auto-exit disabled
- `ArgvInputSanitizer` normalizes `phel run` arguments, separating script options from command arguments via `--`
- `FilesystemFacade.clearAll()` is called after execution for cleanup
