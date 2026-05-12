# Filesystem Module

File system abstraction for compilation: temp directory management and compiled artifact tracking.

## Gacela Pattern

- **Facade**: `FilesystemFacade` implements `FilesystemFacadeInterface`
- **Factory**: `FilesystemFactory` extends `AbstractFactory<FilesystemConfig>`
- **Config**: `FilesystemConfig` : reads `KEEP_GENERATED_TEMP_FILES` (bool) and `TEMP_DIR` (string)
- **No Provider** : no inter-module dependencies

## Public API (Facade)

- `addFile(string $file): void` : register a compiled file for tracking
- `clearAll(): void` : delete all tracked files
- `getTempDir(): string` : get or create temporary directory
- `getHealthCheck(): ModuleHealthCheckInterface` : Gacela health check that verifies the temp dir exists and is writable; consumed by `phel doctor`

## Dependencies

- **Config** (`PhelConfig`) : configuration values
- **Shared** : `FileException` exception type

## Structure

```
Filesystem/
├── Application/        FileIo (wraps is_writable), TempDirFinder, TempDirHealthCheck
├── Domain/             FilesystemInterface, FileIoInterface, NullFilesystem
├── Infrastructure/     RealFilesystem (tracks files in static array)
└── Gacela files        FilesystemFacade, FilesystemFactory, FilesystemConfig
```

## Key Constraints

- **Strategy pattern**: `RealFilesystem` (normal) vs `NullFilesystem` (when `KEEP_GENERATED_TEMP_FILES = true`)
- `RealFilesystem` uses a static array to track files
- `TempDirFinder` throws `FileException` on permission failures
- `Phel\Shared\PhelProjectDirectory::ensure()` is the single entry point for creating `.phel/`. Best-effort: never throws. Seeds `.gitignore` on first create. On read-only filesystems, silently no-ops.
