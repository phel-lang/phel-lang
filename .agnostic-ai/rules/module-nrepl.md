---
name: module-nrepl
description: 'Load when changing src/php/Nrepl: nREPL server, ops, sessions, bencode, port file.'
scope: src/php/Nrepl
---

# Nrepl Module

nREPL server: bencode over TCP for Cursive, Calva, CIDER, Conjure. `NreplConfig`: port 7888, host 127.0.0.1.

## Facade (internal CLI plumbing, outside public semver, ADR 0021)

| Method | Purpose |
|--------|---------|
| `createSocketServer(int $port, string $host, ?callable $logger): NreplSocketServer` | Build the TCP server |
| `loadPhelNamespaces(): void` | Delegates to RunFacade |

`NreplFactory::createOpDispatcher()` stays internal wiring; tests build an `OpDispatcher` directly (`tests/php/Unit/Nrepl/Domain/Op/OpDispatcherTest.php`).

## Ops

| Op | Source |
|----|--------|
| `clone` `close` `describe` `eval` `load-file` `interrupt` | local handlers |
| `completions` | Api `replCompleteWithTypes` |
| `lookup` / `info` / `eldoc` | Api `findSymbolMetadata` (three `LookupOp` instances differing by name) |
| `reload` | Run `structuredEval` to `phel.repl/reload!`; `all` param (`1`/`true`) to `reload-all!` |
| `run-tests` | Run `structuredEval` to `phel.repl/run-tests` (required `ns`); `var` param to `phel.repl/run-test` |

## Dependencies (NreplProvider)

| Provider key | Used for |
|--------------|----------|
| `RunFacadeInterface::class` | `structuredEval`, version, `loadPhelNamespaces` |
| `ApiFacadeInterface::class` | completion, symbol metadata |
| `CompilerFacadeInterface::class` | reading and writing the current namespace on the global environment around each eval |

## Structure

- `Domain/BindAddress`: host validation
- `Domain/Bencode/`: codec (encoder, decoder, stream-decoder, exception)
- `Domain/Op/`: `OpDispatcher`, `OpHandlerInterface`, `OpRequest`, `OpResponse`, `OpStatus`
- `Domain/Session/`: `Session`, `SessionRegistry`
- `Domain/Transport/`: `ClientConnection`
- `Application/Op/`: one class per op (+ `EvalResultResponder`)
- `Application/Session/`: `SessionNamespaceBinder`
- `Infrastructure/`: `NreplSocketServer`, `ClientFiberPool`, `NreplPortFile`, `Command/NreplCommand`

## Key Constraints

- The bencode codec has zero dependencies.
- Each op implements `OpHandlerInterface`; the dispatcher maps op name to handler.
- One PHP Fiber per connection (`ClientFiberPool`, cooperative suspend).
- Eval always via RunFacade; no inline compilation.
- `LookupOp` resolves namespace: explicit param, else session, else `"user"`.
- `Session` tracks id, namespace, and a 3-deep value ring (`value(1..3)`; `lastValue()` is `value(1)`). `EvalResultResponder` surfaces it as `*1`/`*2`/`*3` in each successful session eval response. `*e` stays REPL-only.
- **The namespace is per session; definitions are not.** `SessionNamespaceBinder` owns both directions. `EvalOp`/`LoadFileOp` call `bind()` before evaluating; `EvalResultResponder` calls `sync()` afterwards to mirror the result back and fill the `ns` response field that drives editor prompts. Without `bind()`, A evaluating `(ns foo)` makes B's next form compile in `foo` (#2906). The registry stays global: a `def` in one session is visible from the other; only the *current* namespace is isolated.
- **"The current namespace" is two pieces of state**, and `bind()` writes both: the analyzer's `GlobalEnvironment::ns` (where code compiles; read back by `sync()`) and the runtime `phel.core/*ns*` var via `Phel::setVar`. `NamespaceLoader::restoreStartupNamespace()` writes the same pair.
- Attach `ns` to eval error frames. `EvalOp` answers empty `code` with a no-op `done` frame carrying the `ns` (CIDER's `cider-repl-init-code` primes its prompt from the first eval response, whatever its outcome). A failed or incomplete eval restores the evaluator's environment snapshot, so the session does not move.
- `NreplSocketServer::run(int $maxIterations = 0)` bounds test runs; `0` = unbounded.
- `NreplCommand` writes the bound port to `.nrepl-port` in the working directory once the socket listens, so `--port=0` records the real port. Remove it on every exit path: `finally` around `run()`, a `register_shutdown_function` backstop for fatal exits, and SIGINT/SIGTERM handlers that call `stop()` (no-op without ext-pcntl, i.e. Windows). `NreplPortFile::delete()` only removes a file the same instance wrote, so a server that failed before writing never deletes another server's file.
