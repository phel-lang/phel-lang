---
name: module-lsp
description: 'Load when editing src/php/Lsp: the Language Server Protocol v3.17 server over stdio.'
scope: src/php/Lsp
---

# Lsp Module

`phel lsp`: LSP v3.17 over stdio (JSON-RPC 2.0), a thin transport over the Api, Lint, Formatter and Run facades. Internal CLI plumbing, outside public semver (ADR 0021).

## Facade and dependencies

- `createServer($output)` binds the notification stream; the caller owns the loop via `LspServer::serve($input, $output)`. `LspFactory::createDispatcher()` stays internal; tests build a `RequestDispatcher` directly.
- Lint is injected as the concrete `LintFacade`, the codebase's one concrete binding (`.agnostic-ai/rules/module-map.md`). A Lint contract is leaf-safe only once `RuleSettings`, `LintCache` and `LintResult` move to Shared too.

## Key Constraints

- Framing is strict `Content-Length: <n>\r\n\r\n<body>`.
- New LSP method: a `HandlerInterface` class in `Application/Handler/`, registered in `LspFactory::createDispatcher()`. Handlers never touch transport: return via `RequestDispatcher` or push via `Session::sink()`.
- `DocumentStore` is authoritative for open-file content. Lint diagnostics use the buffer through `LintFacade::lintSource()`, never saved source or the lint result cache.
- One `DiagnosticPublisher` is shared by server and handlers. An edit replaces the queued document per URI and resets its debounce deadline (200 ms, `LspConfig`); the server calls `publishDue()` between reads. Open and save publish immediately and cancel pending work; close cancels before clearing diagnostics. Notifications carry the document version.
- `MessageReader` keeps partial headers and bodies across bounded readiness waits, so idle wakeups still flush queued diagnostics. It drains buffered frames before EOF and reports unsupported readiness as a transport failure.
- `Application/Convert/` stays pure (no facade state). `PositionConverter` is the only place 1-based `Diagnostic`/`Location`/`Definition` become 0-based LSP positions (`DiagnosticRangeLspTest`).
- Completion, hover and signature help try PHP interop through the Api facade first, then Phel symbols.
- Variable completions (`php/$_SERVER`) are the only items with an explicit `textEdit`: a client's word range stops at `$`, so plain `insertText` would give `php/$$_SERVER`. `CompletionHandler` measures the `$...` token but does not re-check the surrounding `php/`; that grammar lives in Api's `PhpInteropContextResolver`, do not fork it here.
