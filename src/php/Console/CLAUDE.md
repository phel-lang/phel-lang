# Console Module

CLI application entry point: bootstraps Symfony Console, registers commands, detects version.

## Gacela Pattern

- **Facade**: `ConsoleFacade` implements `ConsoleFacadeInterface`
- **Factory**: `ConsoleFactory` creates `ConsoleBootstrap`, `ArgvInputSanitizer`, `VersionResolver` (from `Phel\Shared`)
- **Provider**: `ConsoleProvider` injects `COMMANDS`, `FACADE_FILESYSTEM`

## Public API (Facade)

- `getVersion(): string` : full version (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`)
- `runConsole(): void` : execute the CLI application

## CLI Commands

Registered via `ConsoleProvider::COMMANDS` from per-module `ConsoleCommandProviderInterface` impls in `Infrastructure/Command/*Commands.php`:

- **Run**: init, agent-install, ns, repl, eval, compile, run, test, test-worker, doctor
- **Api**: doc, analyze, index, api-daemon
- **Build**: build, cache:clear
- **Formatter**: format
- **Interop**: export
- **Lint**: lint
- **Lsp**: lsp
- **Nrepl**: nrepl
- **Profile**: profile
- **Watch**: watch
- **Framework**: cache:warm, debug:container, debug:dependencies, debug:modules, list:modules, profile:report, validate:config

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
- `ConsoleBootstrap.run()` calls `FilesystemFacade.clearAll()` then exits manually
- `ArgvInputSanitizer` normalizes arguments, separating script options from command arguments via `--`
- `WarnDeprecationsFlag.applyAndStrip()` processes deprecation notices from argv
