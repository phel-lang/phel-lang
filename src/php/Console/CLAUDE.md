# Console Module

CLI entry point: bootstraps Symfony Console, lazily registers every module's commands, resolves version.

## Public API (Facade)

| Method | Returns | Notes |
|--------|---------|-------|
| `getVersion()` | `string` | Full (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`); via `Phel\Shared\VersionResolver` |
| `runConsole()` | `void` | Build + run the CLI app; does not return (see ConsoleBootstrap) |

## Dependencies

| Provider constant | Facade | Used for |
|-------------------|--------|----------|
| `FACADE_FILESYSTEM` | Filesystem | `clearAll()` cleanup after each run |

## Structure

| Path | Role |
|------|------|
| `Infrastructure/ConsoleBootstrap.php` | Extends Symfony `Application`; owns the run lifecycle |
| `Infrastructure/Command/LazyCommandLoader.php` | Symfony `CommandLoaderInterface`; wired via `setCommandLoader()` |
| `Infrastructure/Command/*Commands.php` | One `ConsoleCommandProviderInterface` impl per module |
| `Domain/ConsoleCommandProviderInterface.php` | `lazyCommands(): list<LazyCommand>` |
| `Application/ArgvInputSanitizer.php` | Normalizes argv; splits script options from command args via `--` |
| `Application/WarnDeprecationsFlag.php` | `applyAndStrip()` consumes deprecation flags from argv |

## CLI Commands (lazy)

- Sibling Command classes (in other modules) are NOT injected via Facade; each per-module `*Commands.php` provider wraps them as Symfony `LazyCommand`s.
- Command providers: Run, Interop, Formatter, Api, Build, Framework (debug), Nrepl, Lint, Profile, Lsp, Watch — order set by `ConsoleProvider::commandProviders()`; command order follows that list.
- `LazyCommand` wrappers carry name/aliases/description/hidden up front, so `list`/`help`/alias resolution work without constructing every command — only the dispatched one is built per invocation.
- `ConsoleProvider::LAZY_COMMANDS` aggregates all providers' commands; `ConsoleFactory::createCommandLoader()` feeds them to `LazyCommandLoader`.

## Key Constraints

- Default command is `repl` (`setDefaultCommand('repl')` when no command given).
- `ConsoleBootstrap::run()` does NOT return: runs with auto-exit disabled, then `FilesystemFacade::clearAll()`, then `exit($exitCode)`. Code after the call site is unreachable.
- Bare top-level `--help`/`-h` (no command) is rewritten to the `list` command so it shows all commands, not repl help.
- Lazy metadata is drift-guarded by `tests/php/Integration/Console/LazyCommandMetadataTest.php`: builds each command and asserts the wrapper matches `configure()`. Keep wrapper metadata in `*Commands.php` in sync with each command's `configure()`.
