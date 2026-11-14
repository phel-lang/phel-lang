---
name: module-watch
description: 'Load when editing src/php/Watch: the hot-reload file watcher behind `phel watch`.'
scope: src/php/Watch
---

# Watch Module

Hot-reload file watcher: detects `.phel` changes and re-evaluates affected namespaces in dependency order.

## Internal tooling API (Facade)

| Method | Purpose |
|--------|---------|
| `watch(array $paths, array $options = []): void` | Blocking watch loop. Options: `backend` (auto\|inotify\|fswatch\|polling), `poll` (ms), `debounce` (ms), `publisher` (`?ReloadEventPublisherInterface`) |
| `createFileWatcher(?string $preferred, ?int $pollMs, ?int $debounceMs)` | Build a single watcher for the preferred backend |

Production surface only. `WatchFactory::createFileWatcherBuilder()`, `createReloadOrchestrator()` and `createNamespaceResolver()` stay internal wiring; tests build those collaborators directly (`FileWatcherBuilderTest`, `ReloadOrchestratorTest`, `NamespaceResolverTest`).

CLI: `./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]` (`Infrastructure/Command/WatchCommand`).

Internal CLI plumbing, outside the public PHP semver surface (ADR 0021).

## Dependencies (WatchProvider)

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Run | `RunFacadeInterface` | `evalFile`, `structuredEval` (reload hooks) |
| Build | `BuildFacadeInterface` | `getDependenciesForNamespace` (dep-order reload) |
| Api | `ApiFacadeInterface` | `indexProject`; incremental re-index for tooling |
| Command | `CommandFacadeInterface` | source-directory defaults |

All four getters return the Shared contract, never a concrete facade; `SatelliteFactoryFacadeInjectionTest` pins the return types.

## Structure

| Path | Role |
|------|------|
| `Application/ReloadOrchestrator.php` | Reload side-effect pipeline (see constraints) |
| `Application/NamespaceResolver.php` | Regex `ns`/`in-ns` extractor, not the parser |
| `Application/Watcher/{Inotify,Fswatch,Polling}Watcher.php` | Backend strategies (`FileWatcherInterface`) |
| `Application/Watcher/EventDebouncer.php` | Coalesces events within the debounce window |
| `Application/Watcher/FileWatcherBuilder.php` | Backend selection (auto-probe) |
| `Application/{ApiProjectReindexer,MtimeFileSystemScanner,SystemClock,NullReloadEventPublisher}.php` | Adapters |
| `Application/WatchRunner.php` | Wires watcher → orchestrator |
| `WatchConfig.php` | Defaults: poll 500ms, debounce 100ms, backend `auto` |

## Key Constraints

- Backends are strategies. Only `PollingWatcher` runs against a real filesystem in CI; `InotifyWatcher`/`FswatchWatcher` are runtime-probed, and `ShellWatcherIntegrationTest` drives them through fake binaries that print each tool's output format.
- `AbstractShellWatcher` buffers stdout and splits it on `recordSeparator()` (newline, NUL for fswatch `-0`); a non-blocking read can stop mid-record, so never parse a raw read.
- Debounce coalesces rapid changes so an editor double-save triggers one reload.
- `ReloadOrchestrator.handleChanges` order: resolve namespaces → reload in dep order → run `(phel.watch/run-on-reload-hooks <ns>)` per ns → re-index → publish event. Reload, hooks and reindex are best-effort (catch `Throwable`) so one broken file never kills the loop. Deleted files are skipped.
- `NullReloadEventPublisher` is the default; inject an nREPL-aware publisher for nREPL contexts.
- `NamespaceResolver` uses a regex on the first `ns`/`in-ns` form (runs on every change: speed over full parse), not the compiler reader.
