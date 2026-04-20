# Nrepl Module

nREPL protocol server: bencode-over-TCP wire protocol for editor tooling (Cursive, Calva, CIDER, Conjure).

## Gacela Pattern

- **Facade**: `NreplFacade` extends `AbstractFacade<NreplFactory>`
- **Factory**: `NreplFactory` extends `AbstractFactory<NreplConfig>`
- **Config**: `NreplConfig` — default port `7888`, default host `127.0.0.1`
- **Provider**: `NreplProvider` — injects `FACADE_RUN` and `FACADE_API`

## Public API (Facade)

- `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer`
- `createOpDispatcher(): OpDispatcher` — exposed for testing and for reuse from non-socket transports
- `loadPhelNamespaces(): void` — delegates to `RunFacade::loadPhelNamespaces`

## CLI Command

`./bin/phel nrepl --port=<N> --host=<addr>` — starts the TCP server.

## Supported Ops

- Core: `clone`, `close`, `describe`, `eval`, `load-file`, `interrupt`
- Tooling: `completions` (via `ApiFacade::replCompleteWithTypes`), `lookup`, `info`, `eldoc` (via `ApiFacade::getPhelFunctions`)

## Dependencies

- **Run** (`RunFacade`) — `structuredEval`, `getVersion`, `loadPhelNamespaces`
- **Api** (`ApiFacade`) — `replCompleteWithTypes`, `getPhelFunctions`
- **Printer** — `Printer::readable()` for serialising eval results
- **Shared** — `RunFacadeInterface`, `ApiFacadeInterface`

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

- The bencode codec is a pure library: zero dependencies on Gacela or other modules, reusable in isolation
- Each op is a single-responsibility class implementing `OpHandlerInterface`; dispatch is a name-to-handler map
- The accept loop uses PHP Fibers: one Fiber per connected client, cooperative yielding via `Fiber::suspend`
- Eval delegates to `RunFacade::structuredEval` — never reimplements the compiler
- Session state tracks id, namespace, and the last evaluated value (for future `*1`/`*2`/`*3` wiring)
- `NreplSocketServer::run($maxIterations)` takes an iteration cap for test-driven runs; `0` means unbounded
