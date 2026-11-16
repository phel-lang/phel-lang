---
name: module-shared-process
description: 'Load when editing src/php/Shared/Performance or Shared/Process: OPcache helpers, worker framing, git and CPU probes.'
scope: src/php/Shared/Performance
globs:
  - src/php/Shared/Process/**
---

# Shared Performance and Process

Part of the Shared module (`.agnostic-ai/rules/module-shared.md`). Pure or read-only helpers; `Performance/*` is `@internal`.

## OPcache (`Performance/`)

- `OpcacheReexec::decide()` decides whether `bin/phel` should `pcntl_exec` itself with a file cache; the exec itself stays in `bin/phel`. `OpcacheReexecDecision::withUserIniFlags()` puts user flags **before** Phel's, so PHP's last-wins `-d` keeps `opcache.enable_cli` / `file_cache` / `file_cache_only` authoritative.
- `UserIniFlags::extract()` recovers the user's `-d`/`-n`/`-c` flags from `ProcessCommandLine::current()` (OS argv: PHP strips interpreter flags from `$_SERVER['argv']`); returns `[]` when the two argv views are ambiguous.
- `OpcacheWorkerFlags::forFileCache()` returns `[]` without OPcache or a cache dir: PHP aborts at startup on an empty `opcache.file_cache`. Used by the re-exec and Run's test workers.
- `OpcacheFileCache` layout: `<file_cache>/<32-char system id><absolute source path>.bin`, so a PHAR source yields a `<id>phar:` entry.
- `OpcacheFileCachePruner::clearContents()` empties the dir without removing it (PHP aborts when `opcache.file_cache` is missing). Symlinked dirs are unlinked, never descended.

## Workers (`Process/`)

- `WorkerFrame`: length-prefixed JSON (`<8 hex digits>\n<payload>`) over worker stdin/stdout, shared by `_test-worker` (Run) and `_mutate-worker` (Mutate).
- `PhelBinaryLocator::locate()`: `SCRIPT_FILENAME`, else `argv[0]`, else repo `bin/phel`, so workers run the parent's code and config. Shared because `RunFactory` and `MutateFactory` may not reach into each other.
- `CpuCountDetector`: `PHEL_TEST_WORKERS` overrides; else system probes, else 4. `detect()` caps at 8, `detectMax()` does not.
- `GitChangedFiles::changedFiles()`: uncommitted + untracked; else since the default branch's merge base; or `git diff <ref>`. Throws `GitUnavailableException` outside a repo. Backs `--changed` in `test` and `mutate`.
