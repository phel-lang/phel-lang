# Lsp Module

Language Server Protocol v3.17 over stdio (JSON-RPC 2.0 with `Content-Length` framing). Thin transport on top of Api, Lint, Formatter, and Run facades.

## Public API (Facade)

- `createServer($input, $output): LspServer`: wire streams and return server instance
- `createDispatcher(): RequestDispatcher`: build dispatcher with all handlers registered (test support)

## Supported LSP Methods

- Lifecycle: initialize, initialized, shutdown, exit
- Text sync: didOpen, didChange, didClose, didSave
- Language features: hover, definition, references, completion, signatureHelp, documentSymbol, workspace/symbol, rename, formatting
- PHP interop (completion/hover/signatureHelp) is resolved by the Api facade (`phpInteropHoverAt`, `phpInteropSignatureAt`; completion via `completeAtPoint`). `CompletionHandler`/`HoverHandler` try the interop path first and fall back to Phel symbols; `SignatureHelpHandler` is interop-only
- Diagnostics: publishDiagnostics (debounced 200ms on change, immediate on save)

## Dependencies

Api (semantic analysis, symbol resolve/references, completion), Lint (rule-based diagnostics), Formatter (string formatting), Run (Phel namespace loading).

## Key Constraints

- Framing: strict `Content-Length: <n>\r\n\r\n<body>` (LSP spec, not newline-delimited or bencode)
- New LSP method: add `HandlerInterface` subclass in `Application/Handler/`, register in `createDispatcher()`
- Handlers avoid transport directly; return via `RequestDispatcher` or push via `Session::sink()`
- `DocumentStore` is authoritative for open-file content
- Diagnostics debounced via `DiagnosticPublisher::shouldPublish()`
- Converters (`Application/Convert/`) are pure (no Facade state), fully testable
