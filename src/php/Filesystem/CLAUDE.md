# Filesystem Module

File system abstraction for compilation: temp directory management and compiled artifact tracking.

## Gacela Pattern

- **Facade**: `FilesystemFacade` implements `FilesystemFacadeInterface`
- **Factory**: `FilesystemFactory` extends `AbstractFactory<FilesystemConfig>`
- **Config**: `FilesystemConfig` — reads `KEEP_GENERATED_TEMP_FILES` (bool) and `TEMP_DIR` (string)
- **No Provider** — no inter-module dependencies

## Public API (Facade)

- `addFile(string $file): void` — register a compiled file for tracking
- `clearAll(): void` — delete all tracked files
- `getTempDir(): string` — get or create temporary directory
- `getHealthCheck(): ModuleHealthCheckInterface` — Gacela health check that verifies the temp dir exists and is writable; consumed by `phel doctor`

## Dependencies

- **Config** (`PhelConfig`) — configuration values
- **Compiler** (`FileException`) — exception type from `Compiler/Domain/Evaluator/Exceptions`

## Structure

```
Filesystem/
├── Application/        FileIo (wraps is_writable), TempDirFinder
├── Domain/             FilesystemInterface, FileIoInterface, NullFilesystem
├── Infrastructure/     RealFilesystem (tracks files in static array)
└── Gacela files        FilesystemFacade, FilesystemFactory, FilesystemConfig
```

## Key Constraints

- **Strategy pattern**: `RealFilesystem` (normal) vs `NullFilesystem` (when `KEEP_GENERATED_TEMP_FILES` is true)
- `RealFilesystem` uses a static array to track files — `clearAll()` deletes them all
- `TempDirFinder` creates/validates temp directory and throws `FileException` on permission failures
- This is a small, focused module — keep it minimal
