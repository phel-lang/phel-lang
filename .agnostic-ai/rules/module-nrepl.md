---
name: module-nrepl
description: 'Load when changing src/php/Nrepl: nREPL server, ops, sessions, bencode, port file.'
scope: src/php/Nrepl
---

# Nrepl Module

`phel nrepl`: bencode over TCP for Cursive, Calva, CIDER, Conjure (default 127.0.0.1:7888). Internal CLI plumbing, outside public semver (ADR 0021). `NreplFactory::createOpDispatcher()` stays internal; tests build an `OpDispatcher` directly.

## Ops

| Op | Source |
|----|--------|
| `completions` | Api `replCompleteWithTypes` |
| `lookup` / `info` / `eldoc` | Api `findSymbolMetadata`; one `LookupOp` class registered three times |
| `reload` | `phel.repl/reload!`; `all` = `1`/`true` calls `reload-all!` |
| `run-tests` | `phel.repl/run-tests` (`ns` required); a `var` param calls `phel.repl/run-test` |
| `phel-inspect-start` / `-page` / `-release` | `InspectOp`: evaluate once, page session-owned handles, release |

New op: implement `OpHandlerInterface`, register in `createOpDispatcher()`.

## Dependencies

`RunFacadeInterface` (`structuredEval`, version, namespace loading), `ApiFacadeInterface` (completion, metadata), `CompilerFacadeInterface` (reading and writing the current namespace around each eval).

## Key Constraints

- Eval always goes through RunFacade; no inline compilation. The bencode codec has zero dependencies. One PHP Fiber per connection (`ClientFiberPool`).
- **The namespace is per session; definitions are not.** `SessionNamespaceBinder` owns both directions: `EvalOp`/`LoadFileOp` call `bind()` before evaluating, `EvalResultResponder` calls `sync()` after to read it back and fill the `ns` response field editors use for prompts. Without `bind()`, A evaluating `(ns foo)` moves B into `foo` (#2906). A `def` in one session is visible from the other.
- **"The current namespace" is two pieces of state**, and `bind()` writes both: the analyzer's `GlobalEnvironment::ns` and the runtime `phel.core/*ns*` var (`Phel::setVar`). `NamespaceLoader::restoreStartupNamespace()` writes the same pair.
- Every eval error frame carries `ns`. Empty `code` gets a no-op `done` frame with `ns` (CIDER primes its prompt from the first eval response). A failed or incomplete eval restores the evaluator's environment snapshot, so the session does not move.
- `LookupOp` namespace: explicit param, else session, else `"user"`.
- `Session` keeps a 3-deep value ring, surfaced as `*1`/`*2`/`*3` in each successful eval response; `*e` stays REPL-only. It also owns up to eight inspections (`MAX_INSPECTIONS`) and clears them on close. Inspection limits and wire contract: `docs/cli-reference.md#incremental-value-inspection`. Inspection bypasses the printer and eval history; PHP properties use raw access; lazy sequences are never counted.
- `NreplSocketServer::run(int $maxIterations = 0)` bounds test runs; `0` = unbounded.
- `.nrepl-port` is written in the working directory once the socket listens, so `--port=0` records the real port. Remove it on every exit path: `finally` around `run()`, a `register_shutdown_function` backstop for fatal exits, and SIGINT/SIGTERM handlers calling `stop()` (no-op without ext-pcntl, so on Windows). `NreplPortFile::delete()` removes only a file the same instance wrote, so a server that failed early never deletes another server's file.
