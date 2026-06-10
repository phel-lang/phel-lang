# Console Module

CLI application entry point: bootstraps Symfony Console, registers commands, detects version.

## Public API (Facade)

- `getVersion(): string`: full version (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`)
- `runConsole(): void`: execute the CLI application

## CLI Commands

Registered via `ConsoleProvider::COMMANDS` from per-module `ConsoleCommandProviderInterface` impls in `Infrastructure/Command/*Commands.php` (one provider per module: Run, Api, Build, Formatter, Interop, Lint, Lsp, Nrepl, Profile, Watch, plus Framework debug commands). Sibling Command classes are wired via these providers, not via Facade injection.

## Dependencies

Filesystem (cleanup after execution). Version detection via `Phel\Shared\VersionResolver`.

## Key Constraints

- Default command is `repl` (when no command specified)
- `ConsoleBootstrap` (extends Symfony Application): `run()` calls `FilesystemFacade.clearAll()` then exits manually
- `ArgvInputSanitizer` normalizes arguments, separating script options from command arguments via `--`
- `WarnDeprecationsFlag.applyAndStrip()` processes deprecation notices from argv
