# Watch Module

Hot-reload and file-watch: detects `.phel` changes and re-evaluates the affected namespaces in dependency order.

## Gacela Pattern

- **Facade**: `WatchFacade` extends `AbstractFacade<WatchFactory>`
- **Factory**: `WatchFactory` extends `AbstractFactory<WatchConfig>`
- **Config**: `WatchConfig` - default poll interval (500ms), debounce (100ms), backend `auto`
- **Provider**: `WatchProvider` - injects `FACADE_RUN`, `FACADE_BUILD`, `FACADE_API`, `FACADE_COMPILER`, `FACADE_COMMAND`

## Public API (Facade)

- `watch(list<string> $paths, array $options = []): void` - blocking watch loop
- `createFileWatcher(?string $backend, ?int $poll, ?int $debounce): FileWatcherInterface`
- `createFileWatcherFactory(?int $poll, ?int $debounce): FileWatcherFactory`
- `createReloadOrchestrator(?ReloadEventPublisherInterface $publisher): ReloadOrchestratorInterface`
- `createNamespaceResolver(): NamespaceResolverInterface`

`$options`: `backend` (`auto|inotify|fswatch|polling`), `poll` (ms), `debounce` (ms), `publisher` (optional `ReloadEventPublisherInterface`).

## CLI Command

`./bin/phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]` - starts the watcher.

## Watcher Backends

Strategy pattern. The factory picks the best available:

- `InotifyWatcher` - Linux, shells out to `inotifywait`
- `FswatchWatcher` - macOS/BSD, shells out to `fswatch`
- `PollingWatcher` - portable fallback, mtime + size diff every poll interval

All three implement `FileWatcherInterface { watch(list<string> $paths, callable $onChange): void; stop(): void; name(): string }`.

## Dependencies

- **Run** (`RunFacade`) - `evalFile`, `structuredEval`, `loadPhelNamespaces`
- **Build** (`BuildFacade`) - `getDependenciesForNamespace`
- **Api** (`ApiFacade`) - `indexProject` for incremental re-index
- **Compiler** (`CompilerFacade`) - CompileOptions
- **Command** (`CommandFacade`) - source-directory defaults

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

- Debounce coalesces events in a 100ms window so editor saves that touch the same file twice trigger a single reload cycle
- `PollingWatcher` is the only backend exercised in CI; `fswatch`/`inotify` availability is probed at runtime but their behaviour relies on external binaries and is not unit-tested
- `ReloadOrchestrator` is the only side-effect surface: reload, run `phel\watch/run-on-reload-hooks`, re-index, publish
- `NullReloadEventPublisher` is the default; swap in an nREPL-aware publisher when the watcher runs inside an nREPL process
- `NamespaceResolver` uses a lightweight regex (not the full parser) since this runs on every file change
