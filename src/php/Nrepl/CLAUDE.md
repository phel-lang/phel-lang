# Nrepl Module

nREPL protocol server; bencode-over-TCP for editor tooling (Cursive, Calva, CIDER, Conjure). `NreplConfig`: port 7888, host 127.0.0.1.

## Public API (Facade)

- `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer`
- `createOpDispatcher(): OpDispatcher` (test/non-socket transport reuse)
- `loadPhelNamespaces(): void` (delegates to RunFacade)

## Supported Ops

Core: `clone`, `close`, `describe`, `eval`, `load-file`, `interrupt`. Via Api facade: `completions` (replCompleteWithTypes), `lookup`/`info`/`eldoc` (findSymbolMetadata).

## Dependencies

Run (structuredEval, getVersion, loadPhelNamespaces), Api (completion, symbol metadata).

## Key Constraints

- Bencode codec (`Domain/Bencode/`) has zero dependencies; reusable standalone
- Each nREPL op implements `OpHandlerInterface`; dispatcher maps name to handler
- Client loop uses PHP Fibers (one per connection, cooperative suspend)
- Eval always via RunFacade (no inline compilation)
- LookupOp resolves namespace: explicit param, else session, else "user"
- Session tracks id, namespace, last values (*1/*2/*3 wiring future)
- `NreplSocketServer::run(maxIterations)` supports test iteration (0 = unbounded)
