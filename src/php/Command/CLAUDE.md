# Command Module

Error reporting, exception formatting, directory discovery.

## Public API (Facade)

- Exceptions: `writeLocatedException`, `writeStackTrace`, `getExceptionString`, `getStackTraceString`, `getExceptionPrinter`
- Directories: `getAllPhelDirectories()` (internal phel + project + vendor), `getSourceDirectories()` (project + vendor), `getProjectSourceDirectories()` (user-configured only), `getTestDirectories()`, `getVendorSourceDirectories()`, `getOutputDirectory()`
- `readPhelConfig(string): array`

## Dependencies

No facade dependencies; Provider exposes `PHP_CONFIG_READER`. Uses Shared (`AbstractLocatedException`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`) and Config (`PhelConfig`, `PhelBuildConfig`).

## Key Constraints

- `DirectoryFinder`: resolves paths, handles PHAR archives, caches results
- `Infrastructure/SourceMapExtractor`: maps compiled PHP back to Phel source locations; reads inline `// `/`// ;;` header comments (eval temp files) or sibling `<file>.map` + `<file>.phel` artifacts (built output)
- `FilePositionExtractor::getFileLineMap()` (exposed via `CommandFacade::getCompiledFileLineMap`): returns the full `[phpLine => phelLine]` map + originating `.phel` filename for a compiled file; used by `phel test --coverage` to enumerate coverable Phel lines
- `TextExceptionPrinter`: renders with syntax highlighting and source pointers; `getUserFacingTraceString()` keeps only Phel fn frames (mapped to `.phel:line`) and collapses PHP-native runs, used by `CommandExceptionWriter::writeStackTrace` for console output (full trace still goes to the error log)
- Config includes stale output recovery hint for corrupted build state
