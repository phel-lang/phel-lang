# Console Module

CLI application entry point: bootstraps Symfony Console, registers commands, detects version.

## Public API (Facade)

- `getVersion(): string`: full version (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`)
- `runConsole(): void`: execute the CLI application

## CLI Commands

Lazy-loaded via `LazyCommandLoader` (`Infrastructure/Command/`), a Symfony `CommandLoaderInterface` that the factory wires onto the application with `setCommandLoader()`. Each per-module `ConsoleCommandProviderInterface` impl in `Infrastructure/Command/*Commands.php` (one provider per module: Run, Api, Build, Formatter, Interop, Lint, Lsp, Nrepl, Profile, Watch, plus Framework debug commands) exposes its commands as `LazyCommand` wrappers carrying name/aliases/description/hidden metadata up front, so only the dispatched command is constructed per invocation while `list`/`help` and alias resolution stay accurate. `ConsoleProvider::LAZY_COMMANDS` aggregates them. Sibling Command classes are wired via these providers, not via Facade injection.

The metadata declared in the providers is drift-guarded by `LazyCommandMetadataTest`, which builds each command and asserts the wrapper matches `configure()`.

## Dependencies

Filesystem (cleanup after execution). Version detection via `Phel\Shared\VersionResolver`.

## Key Constraints

- Default command is `repl` (when no command specified)
- `ConsoleBootstrap` (extends Symfony Application): `run()` calls `FilesystemFacade.clearAll()` then exits manually
- `ArgvInputSanitizer` normalizes arguments, separating script options from command arguments via `--`
- `WarnDeprecationsFlag.applyAndStrip()` processes deprecation notices from argv
