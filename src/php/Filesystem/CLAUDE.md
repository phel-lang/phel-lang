# Filesystem Module

Temp dir management and compiled-artifact cleanup tracking.

## Public API (`FilesystemFacadeInterface`, at module root)

| Method | Returns | Notes |
|---|---|---|
| `addFile(string $file)` | `void` | Register a compiled file for later cleanup |
| `clearAll()` | `void` | `unlink()` all tracked files, reset tracking |
| `getTempDir()` | `string` | Resolve temp dir, creating + caching on first call |
| `getHealthCheck()` | `ModuleHealthCheckInterface` | Gacela probe: temp dir exists + writable |

## Dependencies

None (no Provider). `FilesystemConfig` reads `PhelConfig::KEEP_GENERATED_TEMP_FILES` (bool, default false) and `PhelConfig::TEMP_DIR` (string, default `sys_get_temp_dir()`).

## Structure

| Path | Role |
|---|---|
| `Domain/FilesystemInterface` | Cleanup strategy contract (`addFile`/`clearAll`) |
| `Infrastructure/RealFilesystem` | Active strategy: tracks + deletes files |
| `Domain/NullFilesystem` | No-op strategy when files are kept |
| `Application/TempDirFinder` | Resolve/create/validate temp dir |
| `Application/TempDirHealthCheck` | Health probe (separate from finder) |
| `Application/FileIo` + `Domain/FileIoInterface` | `is_writable` wrapper for testability |

## Key Constraints

- Strategy chosen by `FilesystemFactory::createFilesystem()`: `KEEP_GENERATED_TEMP_FILES=true` → `NullFilesystem` (addFile/clearAll are no-ops, files kept for debug); else `RealFilesystem`.
- `RealFilesystem::$files` is a **static** array — shared across all instances; `addFile`/`clearAll` mutate global process state regardless of instance. `RealFilesystem::reset()` exists to isolate tests.
- `TempDirFinder::getOrCreateTempDir()` caches the path on the instance after first success; idempotent creation tolerates concurrent mkdir; resets umask to 0 around `mkdir(0777)`; on a non-writable dir retries `chmod 0777` once, then throws `FileException`.
- `TempDirHealthCheck` duplicates the finder's create-if-missing logic on purpose so a probe never caches a path on a finder instance.
