# Watch Guide

`phel watch` recompiles and reloads changed namespaces in dependency order, so you can iterate without restarting the REPL or a long-running process. Backends: `inotify` (Linux), `fswatch` (macOS), `polling` (everywhere).

## Contents

- [Quickstart](#quickstart)
- [Options](#options)
- [Programmatic API](#programmatic-api)
- [Pitfalls](#pitfalls)

## Quickstart

```bash
./vendor/bin/phel watch                         # watch configured source dirs
./vendor/bin/phel watch src/ tests/             # watch specific paths
./vendor/bin/phel watch -b polling --poll=250   # force polling, 250ms interval
```

On each change, `phel watch` reloads the changed namespace and its dependents in topological order.

## Options

| Flag | Purpose |
|------|---------|
| `-b, --backend=auto\|inotify\|fswatch\|polling` | override auto-detection |
| `--poll=MS` | polling backend interval in ms (default 500) |
| `--debounce=MS` | collapse rapid events within this window (default 100) |

## Programmatic API

```phel
(ns dev\watcher
  (:require phel\watch :refer [watch!]))

(watch! ["src/" "tests/"])
```

Returns a handle. Stop with `(stop-watch! h)`.

## Pitfalls

- Polling has the highest CPU cost; prefer `inotify` or `fswatch` when available
- Reload follows the dependency graph; cyclic requires break live reload
- Editors that write atomically (rename) emit two events; `--debounce` handles this by default

## See also

- [Linter Guide](./lint-guide.md)
- [LSP Guide](./lsp-guide.md)
