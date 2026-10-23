---
name: module-command
description: 'Command module: Error reporting, exception formatting, and directory discovery for CLI commands.'
scope: src/php/Command
---

# Command Module

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `writeLocatedException(output, e, snippet)` | located exception + hint |
| `writeStackTrace(output, e, showInternalFrames)` | runtime error report to console, full trace to error log |
| `getRuntimeErrorReport(e, showInternalFrames)` | same report as a string (REPL, `eval`) |
| `logStackTrace(e)` | full trace to error log only (REPL, `eval`) |
| `getExceptionString(e, snippet)` / `getStackTraceString(e)` | strings |
| `getExceptionPrinter()` | `ExceptionPrinterInterface` |
| `getExceptionHintResolver()` | `Phel\Shared\Exceptions\Hint\ExceptionHintResolver` |
| `getCompiledFileLineMap(compiledFile)` | `[phpLine => phelLine]` + originating `.phel` filename |
| `getAllPhelDirectories()` | internal phel + project + vendor |
| `getSourceDirectories()` | project + vendor |
| `getProjectSourceDirectories()` | user-configured only |
| `getTestDirectories()` / `getVendorSourceDirectories()` / `getOutputDirectory()` | per name |
| `readPhelConfig(absolutePath)` | parsed `phel-config.php` |
| `reportWarningsAtPhelSource()` | installs `Shared\Exceptions\SourceMappedErrorHandler`: a PHP warning from the eval temp file or compiled cache names the `.phel` line; called once by `bin/phel` (#3537) |

Directory getters are `#[Cacheable]`.

## Dependencies

- No facade dependencies. `CommandProvider` exposes service key `PHP_CONFIG_READER` (Gacela `PhpConfigReader`).
- Shared: `AbstractLocatedException`, `CodeSnippet`, `Printer`, `ColorStyle`, `Munge`, `Exceptions\Hint\*`, `SourceMap\SourceMapConsumer`.
- Config: `PhelConfig`, `PhelBuildConfig`.
- Compiler: one type-only import, `Domain\Evaluator\Exceptions\EvaluatedCodeException` (`instanceof` in `TextExceptionPrinter`, so eval'd frames show Phel source). No compiler facade or calls.

## Structure

| Path | Role |
|------|------|
| `Application/DirectoryFinder` | resolves paths, handles PHAR, caches |
| `Application/CommandExceptionWriter` | writes located exceptions + reports; appends hints |
| `Application/RuntimeErrorReportFormatter` | builds the runtime error report |
| `Application/TextExceptionPrinter` | highlighted render with source pointers |
| `Domain/Exceptions/Extractor/FilePositionExtractor` | compiled→Phel line map |
| `Domain/Exceptions/InternalPathDetector` | Phel's own source and compiled artifacts vs the user's project |
| `Domain/Exceptions/CompiledFnName` | compiled fn class to its Phel var (`app.main/add-it`) via `BOUND_TO` + registry; used by trace frames and `RuntimeArityMessage` (#3460) |
| `Domain/Exceptions/RuntimeArityMessage` | rewrites PHP's `Too few arguments to function ...::__invoke()` as `Wrong number of args (N) passed to ns/fn, expected M` |
| `Domain/Exceptions/EvaluatedCodeLocation` | detects eval'd-code frames, names them `repl` |
| `Infrastructure/SourceMapExtractor` | compiled PHP to Phel source locations |
| `Infrastructure/ComposerVendorDirectoriesFinder` | vendor source dirs |
| `Infrastructure/ErrorLog` | full-trace sink: strips ANSI, heads each entry `[<timestamp>] <command line>`, rotates at 1 MiB into `<log>.1` (one generation) |

## Key Constraints

- `SourceMapExtractor` reads inline `// ` / `// ;;` header comments (eval temp files) OR sibling `<file>.map` + `<file>.phel` (built output). A header kept by `Shared\SourceMap\SupersededSourceMaps` wins over the file on disk.
- `phel test --coverage` enumerates coverable lines via `FilePositionExtractor::getFileLineMap()`; keep its return shape (`[phpLine => phelLine]` + filename) stable.
- `RuntimeErrorReportFormatter` is the ONE report for an uncaught runtime error in `phel run` (`writeStackTrace()`), `phel eval` and the REPL (`getRuntimeErrorReport()`) (#3264). Order, fixed: message, `at`, `data:` (an `ex-info` data map), frames, collapse marker, `hint:`.
- The message starts `[PHELxxx] ` when coded: a located exception's own code, else what `RuntimeErrorCodeResolver` recognises (#3266). Unrecognised exceptions, user `ex-info` included, stay uncoded.
- `TextExceptionPrinter::getUserFacingTraceString()` is the ONE trace filter. It keeps Phel fn frames (as `.phel:line` or `repl`) and counts PHP-native ones into a SINGLE trailing marker; `$showInternalFrames` (`--stack-trace`) shows all. The full trace still goes to the log.
- The marker carries `CommandConfig::getCollapsedTraceHint()` (`--stack-trace to show, full trace in <log>`). One per report, never at the top.
- The report ends on one `hint:` line: an `ExceptionHintResolver` match, else the stale-output hint when anchored on generated PHP with no Phel source to map. `writeLocatedException()` appends the same line.
- `at` anchors on the throw site only if it is the user's. Errors inside `phel\core` walk out to the innermost user `.phel` frame (or `repl`), else the innermost Phel frame, else the throw site. The compiled path prints only for a persistent build artifact, never the eval temp file or compiled cache (`InternalPathDetector`). Stdlib frames stay in the numbered trace.
- Hints are pure utilities in `Phel\Shared\Exceptions\Hint\`, registered in `CommandFactory::createExceptionHints()` (`MissingNsFormHint`, `NotCallableHint`, `ArgumentCountHint`, `UndefinedSymbolHint`, `ClassNotFoundHint`). `MissingNsFormHint` must stay first: `UndefinedSymbolHint` also matches its message.
- Every error path feeds the log: `writeStackTrace()` directly, REPL and `eval` via `logStackTrace()`. Skip it and the marker points at a log without the trace.
- Every writer goes through `ErrorLog::writeln()`, which owns the file shape; writers never strip colour or separate entries themselves. Build it with `CommandFactory::createErrorLog()`.
- `CommandConfig::getStaleOutputHint()` (corrupted build state recovery) uses `getCacheDir()`, which shares `PhelProjectDirectory::resolveCacheDir()` with the build and compiler configs.
