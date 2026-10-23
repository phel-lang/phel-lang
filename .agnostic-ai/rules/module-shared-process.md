---
name: module-shared-process
description: 'Shared Performance and Process: OPcache helpers, worker framing, git and CPU probes.'
scope: src/php/Shared/Performance
globs:
  - src/php/Shared/Process/**
---

# Shared Performance and Process

Part of the Shared module; read `.agnostic-ai/rules/module-shared.md` for its leaf constraints.

Pure or read-only helpers; instantiate directly.

| Class | Public API / purpose |
|-------|----------------------|
| `Performance/OpcacheAdvisor` | pure `advise(...)` (caller passes ini flags) → `OpcacheAdvice`; warns when OPcache won't persist the cache across CLI runs |
| `Performance/OpcacheWorkerFlags` | pure `forFileCache(loaded, dir)` → `-d opcache.enable_cli=1 -d opcache.file_cache=<dir> -d opcache.jit=disable` or `[]`; used by test workers (`RunFactory`) and the CLI re-exec |
| `Performance/OpcacheReexec` | pure `decide(...)` → `OpcacheReexecDecision` (`shouldReexec`, `flags`): whether `bin/phel` should `pcntl_exec` itself with a file cache; the exec stays in `bin/phel`. `withUserIniFlags()` puts user flags **before** Phel's, so PHP's last-wins `-d` keeps `opcache.enable_cli` / `file_cache` / `file_cache_only` authoritative |
| `Performance/OpcacheFileCache` | reads a file cache dir: `entries()`, `detectSystemId(compiledFile)` (probes the `.bin` of an already compiled file), `foreignEntries(id)`, `sizeInBytes()`. Layout `<file_cache>/<32-char system id><absolute source path>.bin`, so a PHAR source yields a `<id>phar:` entry |
| `Performance/OpcacheFileCachePruner` | `pruneForeignSystemIds()`, `pruneOrphanedBins()`, `clearContents()` (empties without removing: PHP aborts when `opcache.file_cache` is missing). Symlinked dirs are unlinked, never descended |
| `Performance/ProcessCommandLine` | `current()` → OS-reported argv (`/proc/self/cmdline`, else `ps -ww -o args=`), or `[]`. Needed because PHP strips interpreter flags from `$_SERVER['argv']` |
| `Performance/UserIniFlags` | pure `extract(processArgs, scriptArgv)` → the user's `-d`/`-n`/`-c` flags, or `[]` when the two argv views are ambiguous. Lets the re-exec keep ini overrides |
| `Process/CpuCountDetector` | worker count: `PHEL_TEST_WORKERS` (`fromEnv()`, via `EnvVar`), else `nproc` / `sysctl` / `/proc/cpuinfo`, else 4; `detect()` caps at 8, `detectMax()` does not. Used by `phel test --parallel` and `phel mutate --parallel` |
| `Process/GitChangedFiles` | `changedFiles(?ref, projectDir)`: absolute changed paths (uncommitted + untracked; else since the default branch's merge base; or `git diff <ref>`); throws `GitUnavailableException` outside a repo. Used by `--changed` in `test` and `mutate` |
| `Process/WorkerFrame` | pure length-prefixed JSON codec (`<8 hex digits>\n<payload>`) over worker stdin/stdout: `encode()`, `readBlocking()`, `decodeBody()`, `headerSize()`. Spoken by `_test-worker` (Run) and `_mutate-worker` (Mutate) |
| `Process/PhelBinaryLocator` | static `locate()` → the running `phel` entry point (`SCRIPT_FILENAME`, else `argv[0]`, else repo `bin/phel`), so workers run the parent's code and config. Shared because `RunFactory` and `MutateFactory` may not reach into each other |
