---
name: module-lsp
description: 'Load when editing src/php/Lsp: the Language Server Protocol v3.17 server over stdio.'
scope: src/php/Lsp
---

# Lsp Module

LSP v3.17 over stdio (JSON-RPC 2.0, `Content-Length` framing). Thin transport on top of Api, Lint, Formatter and Run facades.

## Internal tooling API (Facade)

| Method | Returns | Purpose |
|--------|---------|---------|
| `createServer($output)` | `LspServer` | Bind the notification stream; caller owns the loop via `LspServer::serve($input, $output)` |

Production surface only. `LspFactory::createDispatcher()` stays internal wiring; tests build a `RequestDispatcher` directly (`tests/php/Unit/Lsp/Application/Rpc/RequestDispatcherTest.php`).

Internal CLI plumbing, outside the public PHP semver surface (ADR 0021).

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Api | `ApiFacadeInterface` | Semantic analysis, symbol resolve/references, completion, PHP interop |
| Lint | concrete `LintFacade` | Rule-based diagnostics |
| Formatter | `FormatterFacadeInterface` | String formatting (`formatString`) |
| Run | `RunFacadeInterface` | Phel namespace loading |

`LintFacade` is the last concrete binding in the codebase. Lint has no Shared contract (`.agnostic-ai/rules/module-map.md`), and its methods trade in `RuleSettings`, `LintCache` and `LintResult`, so a contract is only leaf-safe once those move too. `SatelliteFactoryFacadeInjectionTest` pins it and fails on a *second* concrete binding.

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
| `Application/HandlerInterface` | Handler contract; in `Application` because `handle()` takes `Application\Session\Session` |
| `Domain/` | `NotificationSink` |
| `Infrastructure/Command/` | `LspCommand` (`phel lsp`) |

## Key Constraints

- Framing is strict `Content-Length: <n>\r\n\r\n<body>`, not newline-delimited or bencode.
- New LSP method: add a `HandlerInterface` subclass in `Application/Handler/`, register it in `LspFactory::createDispatcher()`.
- Handlers never touch transport; return via `RequestDispatcher` or push via `Session::sink()`.
- `DocumentStore` is authoritative for open-file content.
- Server and handlers share one `DiagnosticPublisher`: edits replace the queued document per URI and reset its 200 ms deadline; the server calls `publishDue()` between reads. Open/save publish immediately and cancel pending work; close cancels before clearing diagnostics. Notifications include the document version.
- `MessageReader` retains partial headers and bodies across bounded readiness waits, keeping idle wakeups for queued diagnostics. It drains buffered frames before EOF and reports unsupported readiness as a transport failure. Lint diagnostics use the document buffer through `LintFacade::lintSource()`, never saved source or the lint result cache.
- Converters in `Application/Convert/` stay pure (no Facade state) to stay unit-testable.
- `Diagnostic`, `Location` and `Definition` arrive 1-based; `PositionConverter` is the only place that makes them 0-based LSP positions. `DiagnosticRangeLspTest` pins that the shift happens once.
- PHP interop (completion/hover/signatureHelp) resolves through the Api facade (`phpInteropHoverAt`, `phpInteropSignatureAt`, `completeAtPoint`). `CompletionHandler`/`HoverHandler`/`SignatureHelpHandler` try interop first, then Phel symbols; signature help reads documented arities via `phelSignatureAt`.
- Variable completions (PHP superglobals, `php/$_SERVER`) are the only kind with an explicit `textEdit`: a client's word range stops at the `$` sigil, so plain `insertText` would give `php/$$_SERVER`. `CompletionHandler` measures the `$...` token; `CompletionConverter` attaches the edit to variable items only. The handler does *not* re-check the surrounding `php/`; that grammar lives in the Api's `PhpInteropContextResolver`, do not fork it here.
