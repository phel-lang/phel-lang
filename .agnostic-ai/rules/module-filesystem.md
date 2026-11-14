---
name: module-filesystem
description: 'Load when editing src/php/Filesystem: temp dir management and compiled-file cleanup tracking.'
scope: src/php/Filesystem
---

# Filesystem Module

Temp dir management and compiled-artifact cleanup tracking.

## Public API (`Shared\Facade\FilesystemFacadeInterface`)

| Method | Returns | Notes |
|---|---|---|
| `addFile(string $file)` | `void` | Register a compiled file for later cleanup |
| `clearAll()` | `void` | Delete this process's tracked files and active eval directories |
| `getTempDir()` | `string` | Resolve temp dir, creating + caching on first call |
| `getProcessTempDir()` | `string` | Get a leased, process-private eval directory below the temp root |
| `getHealthCheck()` | `ModuleHealthCheckInterface` | Temp dir safety/writability and eval-file backlog probe |

## Dependencies

No facades (no Provider). One module import, **Config**: `FilesystemConfig` reads `PhelConfig::KEEP_GENERATED_TEMP_FILES` (bool, default false) and `PhelConfig::TEMP_DIR` (string, default `PhelConfig::defaultTempDir()`, which an array config without the key also gets).

## Structure

| Path | Role |
|---|---|
| `Domain/FilesystemInterface` | Cleanup strategy contract (`addFile`/`clearAll`) |
| `Infrastructure/RealFilesystem` | Active strategy: tracks + deletes files |
| `Domain/NullFilesystem` | No-op strategy when files are kept |
| `Application/TempDirFinder` | Resolve/create/validate temp dir |
| `Application/ProcessTempDirectory` | Per-process eval directories, held ownership leases, bounded dead-owner sweep |
| `Application/TempDirHealthCheck` | Health probe (separate from finder) |
| `Application/FileIo` + `Domain/DirectoryWritabilityCheckerInterface` | `is_writable` wrapper for testability |

## Key Constraints

- `FilesystemFactory::createFilesystem()` picks the strategy: `KEEP_GENERATED_TEMP_FILES=true` → `NullFilesystem` (addFile/clearAll no-ops, files kept for debug); else `RealFilesystem`.
- `RealFilesystem::$files` is shared across instances and keyed by PID, so a fork child cannot clear the parent's files. First registration installs shutdown cleanup; `clearAll()` also releases current-process eval directories. `reset()` drops only this process's file tracking.
- `ProcessTempDirectory` stores eval output under `<temp>/eval/__phel_<pid>_<nonce>/`, with a held `.lock` lease. A short root guard covers directory creation, sweeping and final directory removal, avoiding the mkdir-before-lease and disappearing-directory races. Startup inspects at most 64 entries and deletes at most 2,048 generated files whose lease is unlocked; it never guesses liveness from a PID. A cursor saved in the root guard advances across starts and wraps at the end, so kept/live directories cannot starve later dead owners; seeking may walk raw names, but lease checks and deletion stay bounded. Legacy flat files, unknown contents, symlinks and `.keep` debug directories are preserved. A fork closes inherited descriptors without unlocking the parent's lease and gets its own directory. Shutdown and explicit cleanup affect only the owning PID; a busy root guard leaves an unlocked empty directory for a later sweep.
- `TempDirFinder::getOrCreateTempDir()` caches the path on the instance after first success; creation is idempotent and tolerates concurrent `mkdir(0700)`. It validates ownership and ancestor safety, tightens owned directories to `0700`, and retries a non-writable owned directory with `chmod(0700)` before throwing `FileException`.
- `TempDirHealthCheck` duplicates the finder's create-if-missing logic on purpose so a probe never caches a path on a finder instance.
- The health probe warns after finding more than 1,000 evaluated PHP files, including legacy flat files. Counting stops at 1,001; `phel cache:clear` remains the explicit way to remove a legacy backlog.
