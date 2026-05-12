# Command Module

Foundational infrastructure: error reporting, exception formatting, and project directory management.

## Gacela Pattern

- **Facade**: `CommandFacade` implements `CommandFacadeInterface`
- **Factory**: `CommandFactory` extends `AbstractFactory<CommandConfig>`
- **Config**: `CommandConfig` : source dirs (`src`), test dirs (`tests`), vendor, output, error log
- **Provider**: `CommandProvider` : injects `PHP_CONFIG_READER` from container

## Public API (Facade)

- `writeLocatedException(OutputInterface, AbstractLocatedException, CodeSnippet): void`
- `writeStackTrace(OutputInterface, Throwable): void`
- `getExceptionString(AbstractLocatedException, CodeSnippet): string`
- `getStackTraceString(Throwable): string`
- `getExceptionPrinter(): ExceptionPrinterInterface`
- `getAllPhelDirectories(): array` : source + test + vendor
- `getSourceDirectories(): array`
- `getProjectSourceDirectories(): array` : user-configured only (excludes bundled stdlib)
- `getTestDirectories(): array`
- `getVendorSourceDirectories(): array`
- `getOutputDirectory(): string`
- `readPhelConfig(string): array`

## Dependencies

- **Shared** : `AbstractLocatedException`, `ErrorCode`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`
- **Config** : `PhelConfig`

## Structure

```
Command/
├── Application/        CommandExceptionWriter, DirectoryFinder, TextExceptionPrinter
├── Domain/             CodeDirectories, interfaces, Exceptions/ (extractors, printers)
├── Infrastructure/     ComposerVendorDirectoriesFinder, ErrorLog, SourceMapExtractor
└── Gacela files        CommandFacade, CommandFactory, CommandConfig, CommandProvider
```

## Key Constraints

- Many modules depend on this for error formatting and directories
- `DirectoryFinder` resolves absolute paths and handles PHAR archives
- `SourceMapExtractor` maps generated PHP back to Phel source locations
- `TextExceptionPrinter` renders exceptions with syntax highlighting + source pointers
