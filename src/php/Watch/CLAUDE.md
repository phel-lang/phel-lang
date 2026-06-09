# Watch Module

Hot-reload and file-watch: detects `.phel` changes and re-evaluates affected namespaces in dependency order.

## Public API (Facade)

- `watch(array $paths, array $options = []): void`: blocking watch loop; options: `backend` (auto|inotify|fswatch|polling), `poll` (ms), `debounce` (ms), `publisher` (optional `ReloadEventPublisherInterface`)
- `createFileWatcher(?string $preferred, ?int $pollMs, ?int $debounceMs)`, `createFileWatcherBuilder(...)`
- `createReloadOrchestrator(?ReloadEventPublisherInterface)`, `createNamespaceResolver()`

## CLI

`./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]`

## Dependencies

Run (`evalFile`, `structuredEval`, `loadPhelNamespaces`), Build (`getDependenciesForNamespace`), Api (`indexProject` for incremental re-index), Command (source directory defaults).

## Key Constraints

- Watcher backends are strategies: `InotifyWatcher`, `FswatchWatcher`, `PollingWatcher`. PollingWatcher exercised in CI; inotify/fswatch probed at runtime but not unit-tested (require external binaries)
- Poll interval default 500ms; debounce default 100ms; debounce coalesces rapid changes so editor double-save triggers single reload
- ReloadOrchestrator side-effect surface: reload in dep order, run `phel.watch/run-on-reload-hooks`, re-index, publish event
- NullReloadEventPublisher is default; swap in nREPL-aware publisher for nREPL context
- NamespaceResolver uses lightweight regex for performance on every file change; not full parser
