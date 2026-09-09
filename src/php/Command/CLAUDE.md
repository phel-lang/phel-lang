# Command Module

Error reporting, exception formatting, and directory discovery for CLI commands.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `writeLocatedException(output, e, snippet)` | renders a located exception + hint |
| `writeStackTrace(output, e, showInternalFrames)` | console trace (collapsed, or every frame with `--stack-trace`) + full trace to error log |
| `logStackTrace(e)` | full trace to the error log only, for callers that render the console report themselves (REPL, `eval`) |
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

- No facade dependencies. `CommandProvider` exposes the non-facade service key `PHP_CONFIG_READER` for Gacela's `PhpConfigReader`.
- Shared: `AbstractLocatedException`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`, `Exceptions\Hint\*`, plus `SourceMap\SourceMapConsumer` to map a generated PHP line back to its Phel line.
- Config: `PhelConfig`, `PhelBuildConfig`.
- Compiler: one import, `Domain\Evaluator\Exceptions\EvaluatedCodeException`, tested with `instanceof` in `TextExceptionPrinter` so eval'd frames print with their Phel source. Type-only; Command never calls into the compiler and injects no compiler facade.

## Structure

| Path | Role |
|------|------|
| `Application/DirectoryFinder` | resolves paths, handles PHAR archives, caches results |
| `Application/CommandExceptionWriter` | writes located exceptions + stack traces; appends hints |
| `Application/TextExceptionPrinter` | syntax-highlighted render with source pointers |
| `Domain/Exceptions/Extractor/FilePositionExtractor` | builds the compiled→Phel line map |
| `Domain/Exceptions/InternalPathDetector` | tells Phel's own source and compiled artifacts from the user's project |
| `Infrastructure/SourceMapExtractor` | maps compiled PHP back to Phel source locations |
| `Infrastructure/ComposerVendorDirectoriesFinder` | enumerates vendor source dirs |
| `Infrastructure/ErrorLog` | full-trace sink |

## Key Constraints

- `SourceMapExtractor` reads inline `// ` / `// ;;` header comments (eval temp files) OR sibling `<file>.map` + `<file>.phel` artifacts (built output).
- `FilePositionExtractor::getFileLineMap()` (via `getCompiledFileLineMap`) is used by `phel test --coverage` to enumerate coverable Phel lines — keep its return shape (`[phpLine => phelLine]` + filename) stable.
- `TextExceptionPrinter::getUserFacingTraceString()` is the ONE trace filter behind `phel run`, `phel eval` and the REPL. It keeps only Phel fn frames (mapped to `.phel:line`, or `repl` for eval'd code) and collapses PHP-native runs; `$showInternalFrames` (what `--stack-trace` sets) renders every frame instead. The full trace still goes to the error log.
- The collapse marker carries `CommandConfig::getCollapsedTraceHint()` (`--stack-trace to show, full trace in <log>`) on the FIRST collapsed run only, so a trace with several runs does not repeat the sentence.
- `CommandExceptionWriter` appends an actionable hint (from `ExceptionHintResolver`) after BOTH located-exception and stack-trace output, so failing `phel run`/`test`/`eval` get the same guidance as the REPL.
- `CommandExceptionWriter` anchors the `at` line on the throw site only when it belongs to the user's project; an error raised inside `phel\core` walks out to the innermost user `.phel` frame instead, and the compiled path is printed only for a persistent build artifact, never for the eval temp file or the compiled cache (`InternalPathDetector`). The stdlib frames stay in the numbered trace.
- `CommandExceptionWriter` prints the data map of an uncaught `ex-info` on its own `data:` line.
- Hints are pure utilities in `Phel\Shared\Exceptions\Hint\`. Register new ones in `CommandFactory::createExceptionHints()` (currently `NotCallableHint`, `ArgumentCountHint`, `UndefinedSymbolHint`).
- Every path that reports an error feeds the log: `writeStackTrace()` directly, the REPL and `eval` through `logStackTrace()`. Skipping it makes the collapse marker point at a log that does not have the trace.
- Config carries a stale-output-recovery hint for corrupted build state (`CommandConfig::getStaleOutputHint()`), built on `getCacheDir()`, which shares `PhelProjectDirectory::resolveCacheDir()` with the build and compiler configs.
