# Command Module

Error reporting, exception formatting, directory discovery.

## Gacela Pattern

- **Facade**: `CommandFacade` implements `CommandFacadeInterface`
- **Factory**: `CommandFactory`
- **Config**: `CommandConfig` with code dirs, vendor dir, error log path, stale output hint
- **Provider**: `CommandProvider` provides `PHP_CONFIG_READER`

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `writeLocatedException(OutputInterface, AbstractLocatedException, CodeSnippet)` | `void` |
| `writeStackTrace(OutputInterface, Throwable)` | `void` |
| `getExceptionString(AbstractLocatedException, CodeSnippet)` | `string` |
| `getStackTraceString(Throwable)` | `string` |
| `getExceptionPrinter()` | `ExceptionPrinterInterface` |
| `getAllPhelDirectories()` | `array` (internal phel + project + vendor) |
| `getSourceDirectories()` | `array` (project + vendor) |
| `getProjectSourceDirectories()` | `array` (user-configured only) |
| `getTestDirectories()` | `array` |
| `getVendorSourceDirectories()` | `array` |
| `getOutputDirectory()` | `string` |
| `readPhelConfig(string)` | `array` |

## Dependencies

**Shared**: `AbstractLocatedException`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`
**Config**: `PhelConfig`, `PhelBuildConfig`

## Structure

```
Application/     CommandExceptionWriter, DirectoryFinder, TextExceptionPrinter
Domain/          CodeDirectories, CommandExceptionWriterInterface, Exceptions/, Finder/
Infrastructure/  ComposerVendorDirectoriesFinder, ErrorLog, SourceMapExtractor
```

## Key Constraints

- `DirectoryFinder`: resolves paths, handles PHAR archives, caches results
- `SourceMapExtractor`: maps compiled PHP back to Phel source locations; reads inline `// `/`// ;;` header comments (eval temp files) or sibling `<file>.map` + `<file>.phel` artifacts (built output)
- `TextExceptionPrinter`: renders with syntax highlighting and source pointers
- Config includes stale output recovery hint for corrupted build state
