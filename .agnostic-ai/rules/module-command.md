---
name: module-command
description: 'Load when changing src/php/Command: error reporting, exception formatting, hints, error log, directory discovery for CLI commands.'
scope: src/php/Command
---

# Command Module

Error reporting, exception formatting, hints, error log and directory discovery for CLI commands.

## Facade notes

- `writeStackTrace()` prints the runtime error report and sends the full trace to the error log. `getRuntimeErrorReport()` returns the same report as a string (REPL, `eval`); pair it with `logStackTrace()`.
- `getCompiledFileLineMap()` returns `[phpLine => phelLine]` plus the originating `.phel` filename; `phel test --coverage` depends on that shape.
- Directory getters are `#[Cacheable]`. `getAllPhelDirectories()` is internal + project + vendor, `getSourceDirectories()` project + vendor, `getProjectSourceDirectories()` user-configured only.
- `reportWarningsAtPhelSource()` installs `Shared\Exceptions\SourceMappedErrorHandler`, so a PHP warning from the eval temp file or compiled cache names the `.phel` line. `bin/phel` calls it once.

## Dependencies

- No facades. `CommandProvider` exposes `PHP_CONFIG_READER` (Gacela `PhpConfigReader`).
- Config: `PhelConfig`, `PhelBuildConfig`.
- Compiler: one type-only import, `EvaluatedCodeException` (`instanceof` in `TextExceptionPrinter`). No compiler facade or calls.

## Key Constraints

- `RuntimeErrorReportFormatter` is the ONE report for an uncaught runtime error in `phel run`, `phel eval` and the REPL. Fixed order: message, `at`, `data:` (an `ex-info` data map), frames, collapse marker, `hint:`.
- The message starts `[PHELxxx] ` when coded: a located exception's own code, else what `RuntimeErrorCodeResolver` recognises. Unrecognised exceptions, user `ex-info` included, stay uncoded.
- `TextExceptionPrinter::getUserFacingTraceString()` is the ONE trace filter. It keeps Phel fn frames (as `.phel:line` or `repl`, via `EvaluatedCodeLocation`) and counts PHP-native ones into a SINGLE trailing marker carrying `CommandConfig::getCollapsedTraceHint()`. Never at the top. `$showInternalFrames` (`--stack-trace`) shows all.
- `CompiledFnName` maps a compiled fn class to its Phel var via `BOUND_TO` and the registry, for trace frames and `RuntimeArityMessage` (which rewrites PHP's `Too few arguments ... __invoke()`).
- The report ends on one `hint:` line: an `ExceptionHintResolver` match, else the stale-output hint when anchored on generated PHP with no Phel source to map. `writeLocatedException()` appends the same line.
- `at` anchors on the throw site only if it is the user's. Errors inside `phel\core` walk out to the innermost user `.phel` frame (or `repl`), else the innermost Phel frame, else the throw site. PHP array-parameter errors receiving Phel collections prefer the mapped Phel caller. Print the compiled path only for a persistent build artifact, never the eval temp file or compiled cache (`InternalPathDetector`). Stdlib frames stay in the numbered trace.
- Hints are pure utilities in `Shared\Exceptions\Hint\`, registered in `CommandFactory::createExceptionHints()`. `MissingNsFormHint` stays first: `UndefinedSymbolHint` also matches its message.
- Every error path feeds the log: `writeStackTrace()` directly, REPL and `eval` via `logStackTrace()`. Otherwise the marker points at a log without the trace.
- Every writer goes through `ErrorLog::writeln()`, which owns the file shape (ANSI stripped, `[<timestamp>] <command line>` heading, one rotation at 1 MiB into `<log>.1`). Build it with `CommandFactory::createErrorLog()`.
- `SourceMapExtractor` reads inline `// ` / `// ;;` header comments (eval temp files) OR sibling `<file>.map` + `<file>.phel` (built output). A header kept by `Shared\SourceMap\SupersededSourceMaps` wins over the file on disk.
- `ComposerVendorDirectoriesFinder` silently skips `vendor/phel-lang/phel-lang` (the running Phel under Composer, a second stdlib under a PHAR). `phel doctor` reports the vendored copy; a warning here would repeat on every command.
- `CommandConfig::getStaleOutputHint()` uses `getCacheDir()`, which shares `PhelProjectDirectory::resolveCacheDir()` with the build and compiler configs.
