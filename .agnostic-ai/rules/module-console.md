---
name: module-console
description: 'Load when editing src/php/Console: the CLI entry point and lazy command registration.'
scope: src/php/Console
---

# Console Module

CLI entry point: bootstraps Symfony Console and lazily registers every module's commands.

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `FilesystemFacadeInterface::class` | `clearAll()` after each run |
| `CompilerFacadeInterface::class` | `enableDeprecationWarnings()` when `--warn-deprecations` was passed |
| `ConsoleProvider::LAZY_COMMANDS` | every provider's `LazyCommand`s, fed to `LazyCommandLoader` |

The `Infrastructure/Command/*Commands.php` providers also import the command classes of twelve modules (Api, Balance, Build, Formatter, Interop, Lint, Lsp, Mutate, Nrepl, Profile, Run, Watch) to wrap them.

Console is the graph's sink: nothing depends on it. Keep it so (`module-rules.json`). `Shared/Facade/ConsoleFacadeInterface` exists for a module that must describe console behaviour.

## Key Constraints

- Commands are never injected via a facade. Each provider wraps them as Symfony `LazyCommand`s carrying name, aliases, description and hidden up front, so `list`, `help` and alias resolution work without constructing every command. `ConsoleProvider::commandProviders()` sets the order.
- `tests/php/Integration/Console/LazyCommandMetadataTest.php` builds each command and asserts the wrapper matches `configure()`. Keep wrapper metadata in sync.
- `ConsoleBootstrap::run()` does NOT return: auto-exit off, then `FilesystemFacade::clearAll()`, then `exit`. Codes outside `0..255` become 255 so a failure cannot wrap to success.
- `run()` strips `--warn-deprecations` (`WarnDeprecationsFlag::strip()`) and turns the compiler switch on when the list changed. `ArgvInputSanitizer` splits script options from command args at `--`.
- Default command is `repl`. A bare top-level `--help`/`-h` is rewritten to `list`.
- `doRun()` validates env switches before any command (`OptimizationLevel::resolve`, `EnvVar::WARN_DEPRECATIONS`, `CpuCountDetector::fromEnv`): a bad value exits 2 with one stderr line, not a stack trace mid-compile.
- An unknown command, an unknown option, or a missing or extra argument exits 2 (Symfony's own code is 1): `doRun()` catches `CommandNotFoundException`, and `doRunCommand()` binds and validates input before the command runs (`help` is skipped). Symfony's rendering, "Did you mean" included, goes to stderr.
- `configureIO()` makes Phel's own colour (`NoColor::followOutput`) follow Symfony's stdout decoration decision.
