# Filesystem Module

Temp directory management and compiled artifact tracking.

## Gacela Pattern

- **Facade**: `FilesystemFacade` implements `FilesystemFacadeInterface`
- **Factory**: `FilesystemFactory` extends `AbstractFactory<FilesystemConfig>`
- **Config**: `FilesystemConfig` reads `KEEP_GENERATED_TEMP_FILES` (bool) and `TEMP_DIR` (string)
- **No Provider**: no inter-module dependencies

## Public API (Facade)

- `addFile(string $file): void`: register a compiled file for tracking
- `clearAll(): void`: delete all tracked files
- `getTempDir(): string`: get or create temp directory
- `getHealthCheck(): ModuleHealthCheckInterface`: health check for temp dir existence and writeability

## Dependencies

- `PhelConfig`: configuration values
- `Phel\Shared\Exceptions\FileException`: exception type

## Structure

```
Filesystem/
├── Application/     FileIo, TempDirFinder, TempDirHealthCheck
├── Domain/          FilesystemInterface, FileIoInterface, NullFilesystem
├── Infrastructure/  RealFilesystem
└── Gacela/          FilesystemFacade, FilesystemFactory, FilesystemConfig
```

## Key Constraints

- **Strategy pattern**: `RealFilesystem` (normal) vs `NullFilesystem` (when `KEEP_GENERATED_TEMP_FILES = true`)
- `RealFilesystem` tracks files in static array `$files`
- `TempDirFinder::getOrCreateTempDir()` throws `FileException` on mkdir/chmod failures
- Caching: once created, temp dir path cached in instance variable
