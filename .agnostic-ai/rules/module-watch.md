---
name: module-watch
description: 'Load when editing src/php/Watch: the hot-reload file watcher behind `phel watch`.'
scope: src/php/Watch
---

# Watch Module

Hot-reload file watcher: detects `.phel` changes and re-evaluates affected namespaces in dependency order.

`WatchFacade` has no interface. Internal CLI plumbing, outside the public PHP semver surface (ADR 0021). `watch()` options: `backend` (auto, inotify, fswatch, polling), `poll` (ms), `debounce` (ms), `publisher` (`?ReloadEventPublisherInterface`). Defaults live in `WatchConfig`. The factory's builder, orchestrator and resolver creators stay internal; tests build those collaborators directly.

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `RunFacadeInterface` | `evalFile`, `structuredEval` (reload hooks) |
| `BuildFacadeInterface` | `getDependenciesForNamespace` (dependency-order reload) |
| `ApiFacadeInterface` | `indexProject`, incremental re-index for tooling |
| `CommandFacadeInterface` | source-directory defaults |

Factory getters return the Shared contract; `SatelliteFactoryFacadeInjectionTest` pins this.

`WatchCommand` also calls `\Phel::setupRuntimeArgs()` through the global `\Phel` subclass of the root, which `CompositionRootBoundaryTest` (matches `use Phel\Phel;`) does not see.

## Key Constraints

- Only `PollingWatcher` runs against a real filesystem in CI. `InotifyWatcher` / `FswatchWatcher` are runtime-probed by `FileWatcherBuilder`; `ShellWatcherIntegrationTest` drives them through fake binaries that print each tool's output format.
- `AbstractShellWatcher` buffers stdout and splits on `recordSeparator()` (newline, NUL for fswatch `-0`). A non-blocking read can stop mid-record, so never parse a raw read.
- `EventDebouncer` coalesces rapid changes so an editor double-save triggers one reload.
- `ReloadOrchestrator::handleChanges` order: resolve namespaces, reload in dependency order, run `(phel.watch/run-on-reload-hooks <ns>)` per ns, re-index, publish. Reload, hooks and re-index catch `Throwable` so one broken file never kills the loop. Deleted files are skipped.
- `NullReloadEventPublisher` is the default; inject an nREPL-aware publisher for nREPL contexts.
- `NamespaceResolver` uses a regex on the first `ns`/`in-ns` form, not the compiler reader: it runs on every change, so speed wins over a full parse.
