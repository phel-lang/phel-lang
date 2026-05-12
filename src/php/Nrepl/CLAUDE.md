# Nrepl Module

nREPL protocol server: bencode-over-TCP wire protocol for editor tooling (Cursive, Calva, CIDER, Conjure).

## Gacela Pattern

- **Facade**: `NreplFacade` extends `AbstractFacade<NreplFactory>`
- **Factory**: `NreplFactory` extends `AbstractFactory<NreplConfig>`
- **Config**: `NreplConfig` : default port `7888`, default host `127.0.0.1`
- **Provider**: `NreplProvider` : injects `FACADE_RUN` and `FACADE_API`

## Public API (Facade)

- `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer`
- `createOpDispatcher(): OpDispatcher` : exposed for testing and for reuse from non-socket transports
- `loadPhelNamespaces(): void` : delegates to `RunFacade::loadPhelNamespaces`

## CLI Command

`./bin/phel nrepl --port=<N> --host=<addr>` : starts the TCP server.

## Supported Ops

- Core: `clone`, `close`, `describe`, `eval`, `load-file`, `interrupt`
- Tooling: `completions` (via `ApiFacade::replCompleteWithTypes`), `lookup`, `info`, `eldoc` (via `ApiFacade::findSymbolMetadata` : covers session-defined `defn`s, library defs, and native special forms)

## Dependencies

- **Run** (`FACADE_RUN`) : `structuredEval`, `getVersion`, `loadPhelNamespaces`
- **Api** (`FACADE_API`) : `replCompleteWithTypes`, `findSymbolMetadata`
- **Shared** : exceptions, printer

## Structure

```
Nrepl/
|-- Application/Op/      CloneOp, CloseOp, DescribeOp, EvalOp, LoadFileOp, InterruptOp, CompletionsOp, LookupOp
|-- Domain/
|   |-- Bencode/         BencodeEncoder, BencodeDecoder, BencodeStreamDecoder, BencodeException
|   |-- Op/              OpDispatcher, OpHandlerInterface, OpRequest, OpResponse
|   |-- Session/         Session, SessionRegistry
|   +-- Transport/       ClientConnection
|-- Infrastructure/      NreplSocketServer, Command/NreplCommand
+-- Gacela files         NreplFacade, NreplFactory, NreplConfig, NreplProvider
```

## Key Constraints

- Bencode codec is pure: zero Gacela dependencies, reusable in isolation
- Each op is a single `OpHandlerInterface` class; dispatch is a name-to-handler map
- Accept loop uses PHP Fibers: one per client, cooperative yielding via `Fiber::suspend`
- Eval delegates to `RunFacade::structuredEval` (never reimplements compiler)
- `LookupOp` namespace resolution: explicit `ns` param → session namespace → `"user"`
- Session tracks id, namespace, last evaluated value (for `*1`/`*2`/`*3` future wiring)
- `NreplSocketServer::run(maxIterations)` for test-driven runs; `0` = unbounded
