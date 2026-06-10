# Filesystem Module

Temp directory management and compiled artifact tracking. No Provider (no inter-module dependencies); `FilesystemConfig` reads `KEEP_GENERATED_TEMP_FILES` (bool) and `TEMP_DIR` (string).

## Public API (Facade)

- `addFile(string): void`: register a compiled file for tracking
- `clearAll(): void`: delete all tracked files
- `getTempDir(): string`: get or create temp directory
- `getHealthCheck()`: temp dir existence and writeability

## Key Constraints

- Strategy pattern: `RealFilesystem` (normal) vs `NullFilesystem` (when `KEEP_GENERATED_TEMP_FILES = true`)
- `RealFilesystem` tracks files in static array `$files`
- `TempDirFinder::getOrCreateTempDir()` throws `FileException` on mkdir/chmod failures; once created, temp dir path cached in instance variable
