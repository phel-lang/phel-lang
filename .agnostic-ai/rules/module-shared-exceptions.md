---
name: module-shared-exceptions
description: 'Shared Exceptions: located errors, error codes and hints.'
scope: src/php/Shared/Exceptions
---

# Shared Exceptions

Part of the Shared module; read `.agnostic-ai/rules/module-shared.md` for its leaf constraints.

- `CompilerException`: wraps `AbstractLocatedException` with `CodeSnippet`. `FileException` (file/dir ops); `CompiledCodeIsMalformedException` (PHP `eval()` parse errors).
- `AbstractLocatedException`: base for located errors; carries `SourceLocation` + `ErrorCode`, an optional pre-formatted `relatedLocationNote` that `TextExceptionPrinter` renders under the snippet (`first defined at foo.phel:2`), and `getSuggestions()`: the "did you mean" names (set by `AnalyzerException::cannotResolveSymbol`) so tools need not parse the message.
- `ErrorCode`: enum, five ranges (PHEL0xx analyzer, 1xx parser, 2xx reader, 3xx lexer, 4xx runtime). A case with no production caller fails `ErrorCodeInventoryTest`.
- `SourceMappedErrorHandler` (`@internal`): installed by `bin/phel` through `CommandFacade::reportWarningsAtPhelSource()`. Prints a PHP warning, notice or deprecation from compiled code at the `.phel` file and line its mapper returns; hands unmapped ones back to PHP. Lives here so the compiler's `ErrorNotice` can tell it from a userland handler.
- `RuntimeErrorCodeResolver`: maps an uncaught PHP throwable to its PHEL4xx code, unwrapping one `getPrevious()` level. Only engine classes are recognised; a user's `ex-info` stays uncoded.
- `ErrorCodeCatalog` + `ErrorCodeExplanation`: prose per code; single source for `phel explain` and `composer error-docs:update` (`docs/errors/`). `find()` accepts `PHEL008`, `phel008`, `8`.
- `MissingNsFormException`: thrown by Build's namespace extractor when a file's first form is not `(ns ...)` and analysis hit an unresolved symbol. The analyzer error stays in the previous slot, so the report headline is unchanged; only the hint differs.
- `Exceptions/Hint/`: pure error→hint mappers (`ExceptionHintInterface` + `MissingNsFormHint`, `UndefinedSymbolHint`, `ArgumentCountHint`, `NotCallableHint`, `ClassNotFoundHint`) and `ExceptionHintResolver` (first applicable hint, unwrapping one `getPrevious()` level). Shared so embedding hosts get the same hints.
- `ExceptionPrinterInterface`: implemented by `Command\Application\TextExceptionPrinter`; lives here so `CommandFacadeInterface` does not back-reference `Command\Domain`. Methods: `getExceptionString()`, `getStackTraceString()`, `getUserFacingTraceString($e, $showInternalFrames)` (return strings; the caller picks the output) and side-effecting `printStackTrace()`. Do not re-add `printError()` (bypassed the Symfony `OutputInterface`) or `printException()` (duplicated `CommandExceptionWriter`). `printStackTrace()` looks unused in PHP but `src/phel/test.phel` calls it via interop.
