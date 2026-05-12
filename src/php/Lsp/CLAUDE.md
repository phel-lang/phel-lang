# Lsp Module

Language Server Protocol v3.17 over stdio (JSON-RPC 2.0 with `Content-Length` framing). Thin transport on top of `ApiFacade`, `LintFacade`, and `FormatterFacade`.

## Gacela Pattern

- **Facade**: `LspFacade` extends `AbstractFacade<LspFactory>`
- **Factory**: `LspFactory` extends `AbstractFactory<LspConfig>`
- **Config**: `LspConfig` : default debounce interval, server name/version
- **Provider**: `LspProvider` : injects `FACADE_API`, `FACADE_LINT`, `FACADE_FORMATTER`, `FACADE_RUN`

## Public API (Facade)

- `createServer($input, $output): LspServer` : wire streams + handlers and return the server instance
- `createDispatcher(): RequestDispatcher` : build a dispatcher with every handler registered (exposed for tests)

## CLI Command

`./bin/phel lsp` : starts the server on stdin/stdout.

## Supported LSP Methods

Lifecycle: `initialize`, `initialized`, `shutdown`, `exit`.

Text sync: `textDocument/didOpen`, `textDocument/didChange` (full + incremental), `textDocument/didClose`, `textDocument/didSave`.

Language features:
- `textDocument/hover` : markdown-formatted signature + docstring
- `textDocument/definition` : via `ApiFacade::resolveSymbol`
- `textDocument/references` : via `ApiFacade::findReferences`
- `textDocument/completion` : via `ApiFacade::completeAtPoint`
- `textDocument/documentSymbol` : top-level defs in the open file
- `workspace/symbol` : project-wide symbol search over the cached index
- `textDocument/rename` : reuses `findReferences` to compute a WorkspaceEdit
- `textDocument/formatting` : delegates to `FormatterFacade::formatString`

Diagnostics are published via `textDocument/publishDiagnostics` on didOpen/didChange (debounced ~200ms) and didSave (immediate), combining `ApiFacade::analyzeSource` with `LintFacade::lint`.

## Dependencies (Provider Constants)

- **Api** (`FACADE_API`) : semantic analysis, project index, resolve, references, completion
- **Lint** (`FACADE_LINT`) : rule-based diagnostics
- **Formatter** (`FACADE_FORMATTER`) : `formatString()` for formatting
- **Run** (`FACADE_RUN`) : `loadPhelNamespaces()` for core symbol resolution

## Structure

```
Lsp/
|-- Application/
|   |-- Convert/        PositionConverter, UriConverter, DiagnosticConverter, LocationConverter, CompletionConverter
|   |-- Diagnostics/    DiagnosticPublisher (debounced analyze + lint)
|   |-- Document/       Document, DocumentStore
|   |-- Handler/        One class per LSP method (Initialize, Hover, Definition, ...)
|   |-- Rpc/            MessageReader, MessageWriter, ResponseFactory, RequestDispatcher, StreamNotificationSink, LspServer
|   +-- Session/        Session
|-- Domain/             HandlerInterface, NotificationSink
|-- Infrastructure/
|   +-- Command/        LspCommand (Symfony console)
+-- Gacela files        LspFacade, LspFactory, LspConfig, LspProvider
```

## Key Constraints

- Framing is strict LSP Content-Length: `Content-Length: <n>\r\n\r\n<body>` (not newline-delimited JSON, not bencode)
- New LSP method = new `HandlerInterface` class + one `register(...)` call in `createDispatcher()`
- Handlers never touch transport directly; return payloads or push through `Session::sink()`
- `DocumentStore` is the single source of truth for open-buffer text
- Diagnostics are debounced via `DiagnosticPublisher::shouldPublish()`
- Converters are pure: no stateful Facade dependencies, fully unit-testable
- Cross-module access only through Facade interfaces
