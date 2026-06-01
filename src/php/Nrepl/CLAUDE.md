# Nrepl Module

nREPL protocol server; bencode-over-TCP for editor tooling (Cursive, Calva, CIDER, Conjure).

## Gacela Pattern

| Component | Class | Notes |
|-----------|-------|-------|
| Facade | `NreplFacade` | extends `AbstractFacade<NreplFactory>` |
| Factory | `NreplFactory` | extends `AbstractFactory<NreplConfig>` |
| Config | `NreplConfig` | port 7888, host 127.0.0.1 |
| Provider | `NreplProvider` | injects Run and Api facades |

## Public API (Facade)

- `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer`
- `createOpDispatcher(): OpDispatcher` (test/non-socket transport reuse)
- `loadPhelNamespaces(): void` (delegates to RunFacade)

## Supported Ops

| Op | Via |
|----|-----|
| `clone`, `close`, `describe`, `eval`, `load-file`, `interrupt` | Core |
| `completions` | ApiFacade::replCompleteWithTypes |
| `lookup`, `info`, `eldoc` | ApiFacade::findSymbolMetadata |

## Dependencies

- **Run** (FACADE_RUN): structuredEval, getVersion, loadPhelNamespaces
- **Api** (FACADE_API): replCompleteWithTypes, findSymbolMetadata

## Structure

```
Nrepl/
|-- Application/Op/      Op handlers + EvalResultResponder (one per nREPL op)
|-- Domain/
|   |-- Bencode/         Pure encoder/decoder (Gacela-free)
|   |-- Op/              Dispatcher, request/response, OpHandlerInterface
|   |-- Session/         Registry and session state
|   +-- Transport/       ClientConnection
|-- Infrastructure/      SocketServer, ClientFiberPool, NreplCommand
+-- Gacela files
```

## Key Constraints

- Bencode codec has zero dependencies; reusable standalone
- Each nREPL op implements `OpHandlerInterface`; dispatcher maps name to handler
- Client loop uses PHP Fibers (one per connection, cooperative suspend)
- Eval always via RunFacade (no inline compilation)
- LookupOp resolves namespace: explicit param, else session, else "user"
- Session tracks id, namespace, last values (*1/*2/*3 wiring future)
- `NreplSocketServer::run(maxIterations)` supports test iteration (0 = unbounded)
