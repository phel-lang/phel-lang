# Command Module

Foundational infrastructure: error reporting, exception formatting, and project directory management.

## Gacela Pattern

- **Facade**: `CommandFacade` implements `CommandFacadeInterface`
- **Factory**: `CommandFactory` extends `AbstractFactory<CommandConfig>`
- **Config**: `CommandConfig` — source dirs (`src`), test dirs (`tests`), vendor, output, error log
- **Provider**: `CommandProvider` — injects `PHP_CONFIG_READER` from container

## Public API (Facade)

- `writeLocatedException(OutputInterface, AbstractLocatedException, CodeSnippet): void`
- `writeStackTrace(OutputInterface, Throwable): void`
- `getExceptionString(AbstractLocatedException, CodeSnippet): string`
- `getStackTraceString(Throwable): string`
- `getExceptionPrinter(): ExceptionPrinterInterface`
- `getAllPhelDirectories(): array` — all source + test + vendor directories
- `getSourceDirectories(): array` / `getTestDirectories(): array` / `getVendorSourceDirectories(): array`
- `getProjectSourceDirectories(): array` — user-configured src dirs only (excludes phel's own bundled stdlib dir)
- `getOutputDirectory(): string`
- `readPhelConfig(string): array`

## Dependencies

- **Compiler** — `AbstractLocatedException`, `ErrorCode`, `CodeSnippet`, `MungeInterface`, `SourceLocation`
- **Shared** — `CommandFacadeInterface`, `ColorStyleInterface`
- **Printer** — `Printer` for readable output
- **Config** — `PhelConfig`, `PhelBuildConfig`

## Structure

```
Command/
├── Application/        CommandExceptionWriter, DirectoryFinder, TextExceptionPrinter
├── Domain/             CodeDirectories, interfaces, Exceptions/ (extractors, printers)
├── Infrastructure/     ComposerVendorDirectoriesFinder, ErrorLog, SourceMapExtractor
└── Gacela files        CommandFacade, CommandFactory, CommandConfig, CommandProvider
```

## Key Constraints

- Many modules depend on this for error formatting — changes here ripple widely
- `DirectoryFinder` resolves absolute paths and handles PHAR archives
- `SourceMapExtractor` maps generated PHP back to Phel source locations
- `TextExceptionPrinter` renders exceptions with syntax highlighting and source pointers
