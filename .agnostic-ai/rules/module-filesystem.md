---
name: module-filesystem
description: 'Load when editing src/php/Filesystem: temp dir management and compiled-file cleanup tracking.'
scope: src/php/Filesystem
---

# Filesystem Module

Temp dir management and compiled-artifact cleanup tracking.

## Facade notes

- `clearAll()` deletes this process's tracked files and active eval directories.
- `getProcessTempDir()` returns a leased, process-private eval directory below the temp root.

## Dependencies

No Provider. One module import, **Config**: `FilesystemConfig` reads `PhelConfig::KEEP_GENERATED_TEMP_FILES` (default false) and `PhelConfig::TEMP_DIR` (default `PhelConfig::defaultTempDir()`, also for an array config without the key).

## Key Constraints

- `FilesystemFactory::createFilesystem()`: `KEEP_GENERATED_TEMP_FILES=true` gives `NullFilesystem` (no-ops, files kept for debug), else `RealFilesystem`.
- `RealFilesystem::$files` is shared across instances and keyed by PID, so a fork child cannot clear the parent's files. First registration installs shutdown cleanup; `reset()` drops only this process's tracking.
- `ProcessTempDirectory` stores eval output under `<temp>/eval/__phel_<pid>_<nonce>/` with a held `.lock` lease. A short root guard covers creation, sweeping and final removal, avoiding mkdir-before-lease and disappearing-directory races.
  - Startup inspects at most 64 entries and deletes at most 2,048 files whose lease is unlocked. It never guesses liveness from a PID.
  - A cursor saved in the root guard advances across starts and wraps, so kept or live directories cannot starve later dead owners.
  - Legacy flat files, unknown contents, symlinks and `.keep` debug directories are preserved.
  - A fork closes inherited descriptors without unlocking the parent's lease and gets its own directory. Cleanup affects only the owning PID; a busy root guard leaves an unlocked empty directory for a later sweep.
- `TempDirPolicy` holds the ownership and permission rules shared by `TempDirFinder` and `TempDirHealthCheck`: reject a dir another user owns or can replace, tighten an owned one to `0700`, re-read the mode because some mounts ignore `chmod`.
- `TempDirFinder` caches the path per instance after first success; creation tolerates concurrent `mkdir(0700)`.
- `TempDirHealthCheck` repeats the create-if-missing step on purpose so a probe never caches a path on a finder. It warns past 1,000 evaluated PHP files (legacy flat files included) and stops counting there; `phel cache:clear` removes a legacy backlog.
