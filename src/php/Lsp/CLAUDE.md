# Lsp Module

Language Server Protocol v3.17 over stdio (JSON-RPC 2.0, `Content-Length` framing). Thin transport on top of Api, Lint, Formatter, and Run facades.

## Public API (Facade)

| Method | Returns | Purpose |
|--------|---------|---------|
| `createServer($input, $output)` | `LspServer` | Wire streams; caller owns the loop via `LspServer::serve()` |
| `createDispatcher()` | `RequestDispatcher` | Dispatcher with every handler registered (test support) |

## Dependencies

| Facade | Used for |
|--------|----------|
| Api | Semantic analysis, symbol resolve/references, completion, PHP interop |
| Lint | Rule-based diagnostics |
| Formatter | String formatting |
| Run | Phel namespace loading |

## Supported LSP Methods

- Lifecycle: initialize, initialized, shutdown, exit
- Text sync: didOpen, didChange, didClose, didSave
- Language features: hover, definition, references, completion, signatureHelp, documentSymbol, workspace/symbol, rename, formatting
- Diagnostics: publishDiagnostics (debounced 200ms on change; immediate on save)

## Structure

| Path | Holds |
|------|-------|
| `Application/Rpc/` | `LspServer` (loop), `RequestDispatcher`, `MessageReader`/`Writer`, `StreamNotificationSink` |
| `Application/Handler/` | One `HandlerInterface` per LSP method |
| `Application/Convert/` | Pure LSP↔Phel converters (no Facade state) |
| `Application/Document/` | `DocumentStore`, `Document`, `ContentChangeApplier` |
| `Application/Diagnostics/` | `DiagnosticPublisher` (debounce + publish) |
| `Application/Session/` | `Session` (shutdown state, notification sink, doc store) |
| `Domain/` | `HandlerInterface`, `NotificationSink` |
| `Infrastructure/Command/` | `LspCommand` (`phel lsp`) |

## Key Constraints

- Framing is strict `Content-Length: <n>\r\n\r\n<body>` (LSP spec) — not newline-delimited or bencode.
- New LSP method: add a `HandlerInterface` subclass in `Application/Handler/`, register it in `LspFactory::createDispatcher()`.
- Handlers never touch transport directly — return via `RequestDispatcher` or push via `Session::sink()`.
- `DocumentStore` is authoritative for open-file content.
- Diagnostics are cooperatively debounced: callers check `DiagnosticPublisher::shouldPublish()` before `publish()`; `publishNow()` skips debounce (used on didSave).
- Converters in `Application/Convert/` must stay pure (no Facade state) so they remain unit-testable.
- PHP interop (completion/hover/signatureHelp) resolves through the Api facade (`phpInteropHoverAt`, `phpInteropSignatureAt`, `completeAtPoint`). `CompletionHandler`/`HoverHandler`/`SignatureHelpHandler` try interop first, then fall back to Phel symbols — signature help reads the symbol's documented arities via `phelSignatureAt`.
