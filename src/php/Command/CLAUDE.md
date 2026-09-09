# Command Module

Error reporting, exception formatting, and directory discovery for CLI commands.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `writeLocatedException(output, e, snippet)` | renders a located exception + hint |
| `writeStackTrace(output, e, showInternalFrames)` | the runtime error report to the console + full trace to error log |
| `getRuntimeErrorReport(e, showInternalFrames)` | the same report as a string, for callers that own where the text goes (REPL, `eval`) |
| `logStackTrace(e)` | full trace to the error log only, for those same callers |
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
| `Application/CommandExceptionWriter` | writes located exceptions + runtime error reports; appends hints |
| `Application/RuntimeErrorReportFormatter` | assembles the one runtime error report every command prints |
| `Application/TextExceptionPrinter` | syntax-highlighted render with source pointers |
| `Domain/Exceptions/Extractor/FilePositionExtractor` | builds the compiled→Phel line map |
| `Domain/Exceptions/InternalPathDetector` | tells Phel's own source and compiled artifacts from the user's project |
| `Domain/Exceptions/EvaluatedCodeLocation` | tells a frame of eval'd code from a file, and names it `repl` |
| `Infrastructure/SourceMapExtractor` | maps compiled PHP back to Phel source locations |
| `Infrastructure/ComposerVendorDirectoriesFinder` | enumerates vendor source dirs |
| `Infrastructure/ErrorLog` | full-trace sink |

## Key Constraints

- `SourceMapExtractor` reads inline `// ` / `// ;;` header comments (eval temp files) OR sibling `<file>.map` + `<file>.phel` artifacts (built output).
- `FilePositionExtractor::getFileLineMap()` (via `getCompiledFileLineMap`) is used by `phel test --coverage` to enumerate coverable Phel lines — keep its return shape (`[phpLine => phelLine]` + filename) stable.
- `RuntimeErrorReportFormatter` is the ONE report behind `phel run`, `phel eval` and the REPL, in this order and no other: message, `at`, `data:`, frames, collapse marker, `hint:`. `phel run` writes it through `writeStackTrace()`, the prompt through `getRuntimeErrorReport()`. Three commands used to print three layouts of the same failure (#3264).
- `TextExceptionPrinter::getUserFacingTraceString()` is the ONE trace filter feeding that report. It keeps only Phel fn frames (mapped to `.phel:line`, or `repl` for eval'd code) and counts the PHP-native ones into a SINGLE trailing marker; `$showInternalFrames` (what `--stack-trace` sets) renders every frame instead. The full trace still goes to the error log.
- The collapse marker carries `CommandConfig::getCollapsedTraceHint()` (`--stack-trace to show, full trace in <log>`). One marker per report, so the sentence cannot repeat and no report opens on a frame count.
- The report ends on one `hint:` line: an actionable hint from `ExceptionHintResolver` when one matches, otherwise the stale-output hint when it anchors on generated PHP with no Phel source left to map it back. `writeLocatedException()` appends the same `hint:` line, so a failing compile reads like a failing run.
- The `at` line anchors on the throw site only when it belongs to the user; an error raised inside `phel\core` walks out to the innermost user `.phel` frame, or to `repl` for what was typed at the prompt, and falls back to the innermost Phel frame when the user has none. The compiled path is printed only for a persistent build artifact, never for the eval temp file or the compiled cache (`InternalPathDetector`). The stdlib frames stay in the numbered trace.
- The data map of an uncaught `ex-info` goes on its own `data:` line.
- Hints are pure utilities in `Phel\Shared\Exceptions\Hint\`. Register new ones in `CommandFactory::createExceptionHints()` (currently `NotCallableHint`, `ArgumentCountHint`, `UndefinedSymbolHint`).
- Every path that reports an error feeds the log: `writeStackTrace()` directly, the REPL and `eval` through `logStackTrace()`. Skipping it makes the collapse marker point at a log that does not have the trace.
- Config carries a stale-output-recovery hint for corrupted build state (`CommandConfig::getStaleOutputHint()`), built on `getCacheDir()`, which shares `PhelProjectDirectory::resolveCacheDir()` with the build and compiler configs.
