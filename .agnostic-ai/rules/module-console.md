---
name: module-console
description: 'Load when editing src/php/Console: the CLI entry point and lazy command registration.'
scope: src/php/Console
---

# Console Module

CLI entry point: bootstraps Symfony Console, lazily registers every module's commands, resolves version.

## Public API (Facade)

| Method | Returns | Notes |
|--------|---------|-------|
| `getVersion()` | `string` | Full (`v0.30.0`) or beta with hash (`v0.30.0-beta#abc1234`); via `Phel\Shared\VersionResolver` |
| `runConsole()` | `void` | Build + run the CLI app; does not return (see ConsoleBootstrap) |

## Dependencies

| Provider key | Facade | Used for |
|--------------|--------|----------|
| `FilesystemFacadeInterface::class` | Filesystem | `clearAll()` cleanup after each run |
| `CompilerFacadeInterface::class` | Compiler | `enableDeprecationWarnings()` when `--warn-deprecations` was passed (#3048) |

`FilesystemFacadeInterface::class` is the only injected facade, but not the only edge: `ConsoleFactory` also imports the command classes of twelve modules (Api, Balance, Build, Formatter, Interop, Lint, Lsp, Mutate, Nrepl, Profile, Run, Watch) to register them.

Console is the graph's sink: it depends on nearly everything and **nothing depends on it**. Keep it so. A module importing `Phel\Console` would make a cycle; `ConsoleFacadeInterface` in `Shared/Facade` exists for the rare module that must describe console behaviour.

## Structure

| Path | Role |
|------|------|
| `Infrastructure/ConsoleBootstrap.php` | Extends Symfony `Application`; owns the run lifecycle |
| `Infrastructure/Command/LazyCommandLoader.php` | Symfony `CommandLoaderInterface`; wired via `setCommandLoader()` |
| `Infrastructure/Command/*Commands.php` | One `ConsoleCommandProviderInterface` impl per module |
| `Domain/ConsoleCommandProviderInterface.php` | `lazyCommands(): list<LazyCommand>` |
| `Application/ArgvInputSanitizer.php` | Normalizes argv; splits script options from command args via `--` |
| `Application/WarnDeprecationsFlag.php` | `strip()` removes the deprecation flag from argv; `ConsoleBootstrap` compares the two lists and asks the Compiler facade to turn the switch on |

## CLI Commands (lazy)

- Other modules' Command classes are NOT injected via Facade; each `*Commands.php` provider wraps them as Symfony `LazyCommand`s.
- Command providers: Run, Interop, Formatter, Api, Build, Framework (debug), Nrepl, Lint, Balance, Mutate, Profile, Lsp, Watch (order set by `ConsoleProvider::commandProviders()`).
- `LazyCommand` wrappers carry name/aliases/description/hidden up front, so `list`/`help`/alias resolution work without constructing every command; only the dispatched one is built.
- `ConsoleProvider::LAZY_COMMANDS` aggregates all providers' commands; `ConsoleFactory::createCommandLoader()` feeds them to `LazyCommandLoader`.

## Key Constraints

- Default command is `repl` (`setDefaultCommand('repl')` when no command given).
- `ConsoleBootstrap::run()` does NOT return: runs with auto-exit disabled, then `FilesystemFacade::clearAll()`, then exits. Codes `0..255` are kept; any out-of-range code becomes 255 so a failure cannot wrap to success. Code after the call site is unreachable.
- `ConsoleBootstrap::doRun()` checks `PHEL_OPTIMIZATION_LEVEL` before any command: a bad value exits 2 with one stderr line, not a stack trace mid-compile (#3525).
- Bare top-level `--help`/`-h` (no command) is rewritten to `list`, so it shows all commands, not repl help.
- An unknown command, an unknown option or a missing or extra argument exits 2 (Symfony's own code is 1): `ConsoleBootstrap::doRun()` catches `CommandNotFoundException`, and `doRunCommand()` binds and validates input before the command runs. Symfony's rendering, "Did you mean" included, goes to stderr (#3525).
- `tests/php/Integration/Console/LazyCommandMetadataTest.php` guards drift: it builds each command and asserts the wrapper matches `configure()`. Keep wrapper metadata in `*Commands.php` in sync with each command's `configure()`.
