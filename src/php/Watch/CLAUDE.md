# Watch Module

Hot-reload and file-watch: detects `.phel` changes and re-evaluates affected namespaces in dependency order.

## Gacela Pattern

`WatchFacade` → `WatchFactory` → `WatchConfig`. Provider injects Run, Build, Api, Command facades.

## Public API (Facade)

- `watch(array $paths, array $options = []): void`: blocking watch loop; options: `backend` (auto|inotify|fswatch|polling), `poll` (ms), `debounce` (ms), `publisher` (optional ReloadEventPublisherInterface)
- `createFileWatcher(?string $preferred, ?int $pollMs, ?int $debounceMs): FileWatcherInterface`
- `createFileWatcherBuilder(?int $pollMs, ?int $debounceMs): FileWatcherBuilder`
- `createReloadOrchestrator(?ReloadEventPublisherInterface $publisher): ReloadOrchestratorInterface`
- `createNamespaceResolver(): NamespaceResolverInterface`

## CLI

`./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]`

## Dependencies

| Module | Constant | Use |
|--------|----------|-----|
| Run | `FACADE_RUN` | `evalFile`, `structuredEval`, `loadPhelNamespaces` |
| Build | `FACADE_BUILD` | `getDependenciesForNamespace` |
| Api | `FACADE_API` | `indexProject` for incremental re-index |
| Command | `FACADE_COMMAND` | source directory defaults |

## Structure

```
Watch/
|-- Application/
|   |-- Watcher/
|   |   +-- InotifyWatcher, FswatchWatcher, PollingWatcher (strategy pattern)
|   |   +-- FileWatcherBuilder, EventDebouncer, AbstractShellWatcher
|   |-- NamespaceResolver, ReloadOrchestrator
|   |-- MtimeFileSystemScanner, ApiProjectReindexer
|   |-- SystemClock, NullReloadEventPublisher, WatchRunner
|-- Domain/
|   +-- FileWatcherInterface, NamespaceResolverInterface, ReloadOrchestratorInterface
|   +-- ClockInterface, FileSystemScannerInterface, ReloadEventPublisherInterface, ProjectReindexerInterface
|-- Infrastructure/Command/
|   +-- WatchCommand (Symfony console entry point)
|-- Transfer/
|   +-- WatchEvent
```

## Key Constraints

- Poll interval default 500ms; debounce default 100ms (ConfigWatch)
- Debounce coalesces rapid file changes, so editor double-save triggers single reload
- PollingWatcher exercised in CI; inotify/fswatch probed at runtime but not unit-tested (requires external binaries)
- ReloadOrchestrator side-effect surface: reload in dep order, run phel.watch/run-on-reload-hooks, re-index, publish event
- NullReloadEventPublisher is default; swap in nREPL-aware publisher for nREPL context
- NamespaceResolver uses lightweight regex for performance on every file change; not full parser
