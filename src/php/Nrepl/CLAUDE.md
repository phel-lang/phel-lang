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
| `reload` | Run `structuredEval` → `phel.repl/reload!`; `all` param (`1`/`true`) → `reload-all!` | |
| `run-tests` | Run `structuredEval` → `phel.repl/run-tests` (required `ns` param); add `var` param → `phel.repl/run-test` (single test) | |

## Dependencies (NreplProvider)

| Provider key | Facade | Used for |
|--------------|--------|----------|
| `RunFacadeInterface::class` | RunFacade | `structuredEval`, version, `loadPhelNamespaces` |
| `ApiFacadeInterface::class` | ApiFacade | completion, symbol metadata |
| `CompilerFacadeInterface::class` | CompilerFacade | reading and writing the current namespace on the global environment around each eval |

## Structure

- `Domain/Bencode/` — bencode codec (encoder/decoder/stream-decoder/exception)
- `Domain/Op/` — `OpDispatcher`, `OpHandlerInterface`, `OpRequest`, `OpResponse`, `OpStatus`
- `Domain/Session/` — `Session`, `SessionRegistry`
- `Domain/Transport/` — `ClientConnection`
- `Application/Op/` — one class per op (+ `EvalResultResponder`)
- `Application/Session/` — `SessionNamespaceBinder`
- `Infrastructure/` — `NreplSocketServer`, `ClientFiberPool`, `NreplPortFile`, `Command/NreplCommand`

## Key Constraints

- Bencode codec (`Domain/Bencode/`) has zero dependencies; reusable standalone.
- Each op implements `OpHandlerInterface`; dispatcher maps op name → handler.
- Client loop uses PHP Fibers (one per connection via `ClientFiberPool`, cooperative suspend).
- Eval always via RunFacade — no inline compilation.
- `LookupOp` resolves namespace: explicit param, else session, else `"user"`.
- `Session` tracks id, namespace, and a 3-deep value ring (`value(1..3)`; `lastValue()` is `value(1)`). `EvalResultResponder` surfaces it as `*1`/`*2`/`*3` in each successful eval response (session-scoped; absent for session-less evals). `*e` stays REPL-only.
- **The namespace is per session; definitions are not.** `SessionNamespaceBinder` owns both directions. `EvalOp`/`LoadFileOp` call `bind()` before evaluating, so the code compiles where *that* session left off rather than wherever another session last went; `EvalResultResponder` calls `sync()` afterwards to mirror the result back and fill the `ns` response field that drives editor prompts. Without the bind half, two editors on one server walk over each other: A evaluates `(ns foo)` and B's next form silently compiles in `foo` (#2906). The registry stays global, so a `def` in one session is still visible from the other — only the *current* namespace is isolated.
- **"The current namespace" is two pieces of state**, and `bind()` writes both: the analyzer's `GlobalEnvironment::ns` (where code compiles, and what `sync()` reads back) and the runtime `phel.core/*ns*` var via `Phel::setVar`. Setting only the first leaves `*ns*` reporting the other session's namespace. `NamespaceLoader::restoreStartupNamespace()` writes the same pair.
- The `ns` is also attached to eval error frames, and `EvalOp` answers empty `code` with a no-op `done` frame carrying the `ns`: clients (CIDER's `cider-repl-init-code`) prime their initial prompt namespace from the first eval response on connect, whatever its outcome. A failed or incomplete eval restores the evaluator's environment snapshot, so the session does not move.
- `NreplSocketServer::run(int $maxIterations = 0)` bounds test runs; `0` = unbounded.
- `NreplCommand` writes the bound port to `.nrepl-port` in the working directory (the Clojure-standard discovery file editors read) once the socket is listening, so `--port=0` records the real port. It is removed on every exit path: `finally` around `run()`, a `register_shutdown_function` backstop for fatal exits, and SIGINT/SIGTERM handlers that call `stop()` so the accept loop returns (no-op without ext-pcntl, i.e. on Windows). `NreplPortFile::delete()` only removes a file the same instance wrote, so a server that failed before writing never deletes the file another server is advertising.
