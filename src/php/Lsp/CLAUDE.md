# Lsp Module

Language Server Protocol v3.17 over stdio (JSON-RPC 2.0 with `Content-Length` framing). Thin transport on top of Api, Lint, Formatter, and Run facades.

## Gacela Pattern

- **Facade**: `LspFacade` extends `AbstractFacade<LspFactory>`
- **Factory**: `LspFactory` extends `AbstractFactory<LspConfig>`
- **Config**: `LspConfig` with methods: `defaultDiagnosticDebounceMs()`, `defaultServerName()`, `defaultServerVersion()`
- **Provider**: `LspProvider` injects Api, Lint, Formatter, Run facades via `FACADE_API`, `FACADE_LINT`, `FACADE_FORMATTER`, `FACADE_RUN`

## Public API (Facade)

- `createServer($input, $output): LspServer` - wire streams and return server instance
- `createDispatcher(): RequestDispatcher` - build dispatcher with all handlers registered (test support)

## Supported LSP Methods

| Lifecycle | Text Sync | Language Features | Diagnostics |
|-----------|-----------|-------------------|-------------|
| initialize, initialized, shutdown, exit | didOpen, didChange, didClose, didSave | hover, definition, references, completion, documentSymbol, workspace/symbol, rename, formatting | publishDiagnostics (debounced 200ms on change, immediate on save) |

## Dependencies (Provider Constants)

- `FACADE_API` - semantic analysis, symbol resolve/references, completion
- `FACADE_LINT` - rule-based diagnostics
- `FACADE_FORMATTER` - string formatting
- `FACADE_RUN` - Phel namespace loading

## Structure

```
Lsp/
|-- Application/
|   |-- Convert/         PositionConverter, UriConverter, DiagnosticConverter, LocationConverter, CompletionConverter, SymbolInformationBuilder, SymbolKindMapper
|   |-- Diagnostics/     DiagnosticPublisher
|   |-- Document/        Document, DocumentStore, ContentChangeApplier
|   |-- Handler/         InitializeHandler, InitializedHandler, ShutdownHandler, ExitHandler, DidOpenHandler, DidChangeHandler, DidCloseHandler, DidSaveHandler, HoverHandler, DefinitionHandler, ReferencesHandler, CompletionHandler, DocumentSymbolHandler, WorkspaceSymbolHandler, RenameHandler, FormattingHandler, CursorContext, SymbolResolver
|   |-- Rpc/             LspServer, MessageReader, MessageWriter, RequestDispatcher, StreamNotificationSink, ResponseBuilder, ParamsExtractor
|   +-- Session/         Session
|-- Domain/              HandlerInterface, NotificationSink
|-- Infrastructure/
|   +-- Command/         LspCommand (Symfony console)
+-- Gacela              LspFacade, LspFactory, LspConfig, LspProvider
```

## Key Constraints

- Framing: strict `Content-Length: <n>\r\n\r\n<body>` (LSP spec, not newline-delimited or bencode)
- New LSP method: add `HandlerInterface` subclass, register in `createDispatcher()`
- Handlers avoid transport directly; return via `RequestDispatcher` or push via `Session::sink()`
- `DocumentStore` is authoritative for open-file content
- Diagnostics debounced via `DiagnosticPublisher::shouldPublish()`
- Converters pure (no Facade state), fully testable
- Cross-module access through facades only
