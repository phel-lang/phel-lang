# Nrepl Module

nREPL protocol server: bencode-over-TCP for editor tooling (Cursive, Calva, CIDER, Conjure). `NreplConfig`: port 7888, host 127.0.0.1.

## Public API (Facade)

| Method | Purpose |
|--------|---------|
| `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer` | Build the TCP server |
| `loadPhelNamespaces(): void` | Delegates to RunFacade |

The facade is production surface only. `NreplFactory::createOpDispatcher()` stays internal wiring for `createSocketServer()`; tests build an `OpDispatcher` directly (see `tests/php/Unit/Nrepl/Domain/Op/OpDispatcherTest.php`) rather than going through the facade.

## Supported Ops

| Op | Source | Notes |
|----|--------|-------|
| `clone` `close` `describe` `eval` `load-file` `interrupt` | local handlers | core nREPL |
| `completions` | Api `replCompleteWithTypes` | |
| `lookup` / `info` / `eldoc` | Api `findSymbolMetadata` | three `LookupOp` instances differing only by name |
| `reload` | Run `structuredEval` → `phel\repl/reload!`; `all` param (`1`/`true`) → `reload-all!` | |
| `run-tests` | Run `structuredEval` → `phel\repl/run-tests` (required `ns` param); add `var` param → `phel\repl/run-test` (single test) | |

## Dependencies (NreplProvider)

| Constant | Facade | Used for |
|----------|--------|----------|
| `FACADE_RUN` | RunFacade | `structuredEval`, version, `loadPhelNamespaces` |
| `FACADE_API` | ApiFacade | completion, symbol metadata |
| `FACADE_COMPILER` | CompilerFacade | reading the current namespace from the global environment after eval |

## Structure

- `Domain/Bencode/` — bencode codec (encoder/decoder/stream-decoder/exception)
- `Domain/Op/` — `OpDispatcher`, `OpHandlerInterface`, `OpRequest`, `OpResponse`, `OpStatus`
- `Domain/Session/` — `Session`, `SessionRegistry`
- `Domain/Transport/` — `ClientConnection`
- `Application/Op/` — one class per op (+ `EvalResultResponder`)
- `Infrastructure/` — `NreplSocketServer`, `ClientFiberPool`, `Command/NreplCommand`

## Key Constraints

- Bencode codec (`Domain/Bencode/`) has zero dependencies; reusable standalone.
- Each op implements `OpHandlerInterface`; dispatcher maps op name → handler.
- Client loop uses PHP Fibers (one per connection via `ClientFiberPool`, cooperative suspend).
- Eval always via RunFacade — no inline compilation.
- `LookupOp` resolves namespace: explicit param, else session, else `"user"`.
- `Session` tracks id, namespace, and a 3-deep value ring (`value(1..3)`; `lastValue()` is `value(1)`). `EvalResultResponder` surfaces it as `*1`/`*2`/`*3` in each successful eval response (session-scoped; absent for session-less evals). `*e` stays REPL-only.
- `EvalResultResponder` syncs the session namespace from the compiler's `GlobalEnvironment` after every eval (same source of truth as the terminal REPL prompt), so the `ns` response field — which editor clients use for their prompt — tracks `(ns ...)`/`in-ns` forms.
- `NreplSocketServer::run(int $maxIterations = 0)` bounds test runs; `0` = unbounded.
