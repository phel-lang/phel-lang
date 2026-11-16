---
name: module-shared-exceptions
description: 'Load when editing src/php/Shared/Exceptions: located errors, error codes and hints.'
scope: src/php/Shared/Exceptions
---

# Shared Exceptions

Part of the Shared module (`.agnostic-ai/rules/module-shared.md`).

- `AbstractLocatedException`: carries `SourceLocation` + `ErrorCode`, an optional pre-formatted `relatedLocationNote` (`first defined at foo.phel:2`, rendered under the snippet), and `getSuggestions()` (the "did you mean" names) so tools need not parse the message.
- `ErrorCode`: ranges PHEL0xx analyzer, 1xx parser, 2xx reader, 3xx lexer, 4xx runtime. A case with no production caller fails `ErrorCodeInventoryTest`.
- `ErrorCodeCatalog`: single source for `phel explain` and `composer error-docs:update` (`docs/errors/`).
- `RuntimeErrorCodeResolver` and `Hint/ExceptionHintResolver` unwrap one `getPrevious()` level. The resolver recognises only engine classes; a user's `ex-info` stays uncoded. Hints live here so embedding hosts get the same ones. `MissingLibraryHint` matches the message because REPL and `eval` raise a missing namespace unlocated.
- `MissingNsFormException`: thrown by Build's namespace extractor when a file's first form is not `(ns ...)` and analysis hit an unresolved symbol. The analyzer error stays in the previous slot, so the headline is unchanged; only the hint differs.
- `SourceMappedErrorHandler` (`@internal`): installed by `bin/phel` via `CommandFacade::reportWarningsAtPhelSource()`. Reports a PHP warning, notice or deprecation from compiled code at its `.phel` line; hands unmapped ones back to PHP. Lives here so the compiler's `ErrorNotice` can tell it from a userland handler.
- `ExceptionPrinterInterface`: implemented by Command's `TextExceptionPrinter`; here so `CommandFacadeInterface` does not reference `Command\Domain`. The `get*String()` methods return strings and the caller picks the output. Do not re-add `printError()` (bypassed the Symfony `OutputInterface`) or `printException()` (duplicated `CommandExceptionWriter`). `printStackTrace()` looks unused in PHP, but `src/phel/test.phel` calls it via interop.
