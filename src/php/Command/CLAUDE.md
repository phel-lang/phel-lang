# Command Module

Error reporting, exception formatting, and directory discovery for CLI commands.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `writeLocatedException(output, e, snippet)` | renders a located exception + hint |
| `writeStackTrace(output, e)` | console trace (user-facing) + full trace to error log |
| `getExceptionString(e, snippet)` / `getStackTraceString(e)` | same as above as strings |
| `getExceptionPrinter()` | `ExceptionPrinterInterface` |
| `getExceptionHintResolver()` | shared `Phel\Shared\Exceptions\Hint\ExceptionHintResolver` |
| `getCompiledFileLineMap(compiledFile)` | `[phpLine => phelLine]` map + originating `.phel` filename |
| `getAllPhelDirectories()` | internal phel + project + vendor |
| `getSourceDirectories()` | project + vendor |
| `getProjectSourceDirectories()` | user-configured only |
| `getTestDirectories()` / `getVendorSourceDirectories()` / `getOutputDirectory()` | per name |
| `readPhelConfig(absolutePath)` | parsed `phel-config.php` array |

Directory getters are `#[Cacheable]`.

## Dependencies

- No facade dependencies. Provider exposes `PHP_CONFIG_READER` (Gacela `PhpConfigReader`).
- Shared: `AbstractLocatedException`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`, `Exceptions\Hint\*`.
- Config: `PhelConfig`, `PhelBuildConfig`.

## Structure

| Path | Role |
|------|------|
| `Application/DirectoryFinder` | resolves paths, handles PHAR archives, caches results |
| `Application/CommandExceptionWriter` | writes located exceptions + stack traces; appends hints |
| `Application/TextExceptionPrinter` | syntax-highlighted render with source pointers |
| `Domain/Exceptions/Extractor/FilePositionExtractor` | builds the compiled→Phel line map |
| `Infrastructure/SourceMapExtractor` | maps compiled PHP back to Phel source locations |
| `Infrastructure/ComposerVendorDirectoriesFinder` | enumerates vendor source dirs |
| `Infrastructure/ErrorLog` | full-trace sink |

## Key Constraints

- `SourceMapExtractor` reads inline `// ` / `// ;;` header comments (eval temp files) OR sibling `<file>.map` + `<file>.phel` artifacts (built output).
- `FilePositionExtractor::getFileLineMap()` (via `getCompiledFileLineMap`) is used by `phel test --coverage` to enumerate coverable Phel lines — keep its return shape (`[phpLine => phelLine]` + filename) stable.
- `TextExceptionPrinter::getUserFacingTraceString()` keeps only Phel fn frames (mapped to `.phel:line`) and collapses PHP-native runs; the full trace still goes to the error log.
- `CommandExceptionWriter` appends an actionable hint (from `ExceptionHintResolver`) after BOTH located-exception and stack-trace output, so failing `phel run`/`test`/`eval` get the same guidance as the REPL.
- Hints are pure utilities in `Phel\Shared\Exceptions\Hint\`. Register new ones in `CommandFactory::createExceptionHints()` (currently `NotCallableHint`, `ArgumentCountHint`, `UndefinedSymbolHint`).
- Config carries a stale-output-recovery hint for corrupted build state (`CommandConfig::getStaleOutputHint()`).
