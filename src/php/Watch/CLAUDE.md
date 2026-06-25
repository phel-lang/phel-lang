# Watch Module

Hot-reload file watcher: detects `.phel` changes and re-evaluates affected namespaces in dependency order.

## Public API (Facade)

| Method | Purpose |
|--------|---------|
| `watch(array $paths, array $options = []): void` | Blocking watch loop. Options: `backend` (auto\|inotify\|fswatch\|polling), `poll` (ms), `debounce` (ms), `publisher` (`?ReloadEventPublisherInterface`) |
| `createFileWatcher(?string $preferred, ?int $pollMs, ?int $debounceMs)` | Build a single watcher for the preferred backend |
| `createFileWatcherBuilder(?int $pollMs, ?int $debounceMs)` | Builder that picks a backend |
| `createReloadOrchestrator(?ReloadEventPublisherInterface)` | Reload pipeline (resolve → reload → hooks → reindex → publish) |
| `createNamespaceResolver()` | File-path → namespace resolver |

CLI: `./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]` (`Infrastructure/Command/WatchCommand`).

## Dependencies (Provider FACADE_* constants)

| Facade | Used for |
|--------|----------|
| Run | `evalFile`, `structuredEval` (reload hooks) |
| Build | `getDependenciesForNamespace` (dep-order reload) |
| Api | `indexProject` — incremental re-index for tooling |
| Command | source-directory defaults |

## Structure

| Path | Role |
|------|------|
| `Application/ReloadOrchestrator.php` | Reload side-effect pipeline (see constraints) |
| `Application/NamespaceResolver.php` | Regex `ns`/`in-ns` extractor, not the parser |
| `Application/Watcher/{Inotify,Fswatch,Polling}Watcher.php` | Backend strategies (`FileWatcherInterface`) |
| `Application/Watcher/EventDebouncer.php` | Coalesces events within debounce window |
| `Application/Watcher/FileWatcherBuilder.php` | Backend selection (auto-probe) |
| `Application/{ApiProjectReindexer,MtimeFileSystemScanner,SystemClock,NullReloadEventPublisher}.php` | Adapters |
| `Application/WatchRunner.php` | Wires watcher → orchestrator |
| `WatchConfig.php` | Defaults: poll 500ms, debounce 100ms, backend `auto` |

## Key Constraints

- Backends are strategies. Only `PollingWatcher` runs in CI; `InotifyWatcher`/`FswatchWatcher` are runtime-probed but not unit-tested (need external binaries).
- Debounce coalesces rapid changes so an editor double-save triggers a single reload.
- `ReloadOrchestrator.handleChanges` order: resolve namespaces → reload in dep order → run `(phel\watch/run-on-reload-hooks <ns>)` per ns → re-index → publish event. Reload, hooks, and reindex are best-effort (catch `Throwable`) so one broken file never kills the loop. Deleted files are skipped.
- `NullReloadEventPublisher` is the default; inject an nREPL-aware publisher for nREPL contexts.
- `NamespaceResolver` uses a regex on the first `ns`/`in-ns` form (runs on every change — speed over full parse); not the compiler reader.
