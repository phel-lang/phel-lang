# Watch Module

Hot-reload and file-watch: detects `.phel` changes and re-evaluates the affected namespaces in dependency order.

## Gacela Pattern

- **Facade**: `WatchFacade` extends `AbstractFacade<WatchFactory>`
- **Factory**: `WatchFactory` extends `AbstractFactory<WatchConfig>`
- **Config**: `WatchConfig` - default poll interval (500ms), debounce (100ms), backend `auto`
- **Provider**: `WatchProvider` - injects `FACADE_RUN`, `FACADE_BUILD`, `FACADE_API`, `FACADE_COMMAND`

## Public API (Facade)

- `watch(array, array = []): void` : blocking watch loop
- `createFileWatcher(?string, ?int, ?int): FileWatcherInterface`
- `createFileWatcherBuilder(?int = null, ?int = null): FileWatcherBuilder`
- `createReloadOrchestrator(?ReloadEventPublisherInterface = null): ReloadOrchestratorInterface`
- `createNamespaceResolver(): NamespaceResolverInterface`

`watch()` options: `backend` (`auto|inotify|fswatch|polling`), `poll` (ms), `debounce` (ms), `publisher` (optional).

## CLI Command

`./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]` - starts the watcher.

## Watcher Backends

Strategy pattern. The factory picks the best available:

- `InotifyWatcher` - Linux, shells out to `inotifywait`
- `FswatchWatcher` - macOS/BSD, shells out to `fswatch`
- `PollingWatcher` - portable fallback, mtime + size diff every poll interval

All three implement `FileWatcherInterface { watch(list<string> $paths, callable $onChange): void; stop(): void; name(): string }`.

## Dependencies

- **Run** (`FACADE_RUN`) : `evalFile`, `structuredEval`, `loadPhelNamespaces`
- **Build** (`FACADE_BUILD`) : `getDependenciesForNamespace`
- **Api** (`FACADE_API`) : `indexProject` for incremental re-index
- **Command** (`FACADE_COMMAND`) : source-directory defaults

## Structure

```
Watch/
|-- Application/
|   |-- Watcher/                 InotifyWatcher, FswatchWatcher, PollingWatcher, FileWatcherFactory
|   |-- NamespaceResolver        parses `ns`/`in-ns` from source
|   |-- ReloadOrchestrator       resolves ns, reloads in dep order, publishes event, re-indexes
|   |-- MtimeFileSystemScanner   mtime + size snapshot
|   |-- SystemClock, NullReloadEventPublisher
|-- Domain/                      FileWatcherInterface, ClockInterface, FileSystemScannerInterface,
|                                NamespaceResolverInterface, ReloadOrchestratorInterface,
|                                ReloadEventPublisherInterface
|-- Infrastructure/Command/      WatchCommand (Symfony console)
|-- Transfer/                    WatchEvent
+-- Gacela files                 WatchFacade, WatchFactory, WatchConfig, WatchProvider
```

## Key Constraints

- Debounce coalesces events in a 100ms window so editor double-saves trigger a single reload cycle
- `PollingWatcher` is exercised in CI; `fswatch`/`inotify` probed at runtime but not unit-tested (external binaries)
- `ReloadOrchestrator` is the side-effect surface: reload, run `phel.watch/run-on-reload-hooks`, re-index, publish
- `NullReloadEventPublisher` is the default; swap in an nREPL-aware publisher when the watcher runs inside nREPL
- `NamespaceResolver` uses lightweight regex (not full parser) for performance on every file change
