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
| `clearAll()` | `void` | `unlink()` all tracked files, reset tracking |
| `getTempDir()` | `string` | Resolve temp dir, creating + caching on first call |
| `getHealthCheck()` | `ModuleHealthCheckInterface` | Gacela probe: temp dir exists + writable |

## Dependencies

No facades (no Provider). One module import, **Config**: `FilesystemConfig` reads `PhelConfig::KEEP_GENERATED_TEMP_FILES` (bool, default false) and `PhelConfig::TEMP_DIR` (string, default `PhelConfig::defaultTempDir()`, which an array config without the key also gets).

## Structure

| Path | Role |
|---|---|
| `Domain/FilesystemInterface` | Cleanup strategy contract (`addFile`/`clearAll`) |
| `Infrastructure/RealFilesystem` | Active strategy: tracks + deletes files |
| `Domain/NullFilesystem` | No-op strategy when files are kept |
| `Application/TempDirFinder` | Resolve/create/validate temp dir |
| `Application/TempDirHealthCheck` | Health probe (separate from finder) |
| `Application/FileIo` + `Domain/DirectoryWritabilityCheckerInterface` | `is_writable` wrapper for testability |

## Key Constraints

- `FilesystemFactory::createFilesystem()` picks the strategy: `KEEP_GENERATED_TEMP_FILES=true` → `NullFilesystem` (addFile/clearAll no-ops, files kept for debug); else `RealFilesystem`.
- `RealFilesystem::$files` is a **static** array shared across all instances; `addFile`/`clearAll` mutate global process state. `RealFilesystem::reset()` isolates tests.
- `TempDirFinder::getOrCreateTempDir()` caches the path on the instance after first success; creation is idempotent and tolerates concurrent mkdir; resets umask to 0 around `mkdir(0777)`; on a non-writable dir retries `chmod 0777` once, then throws `FileException`.
- `TempDirHealthCheck` duplicates the finder's create-if-missing logic on purpose so a probe never caches a path on a finder instance.
