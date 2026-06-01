# Config Module

Pure data/model layer defining configuration structure for Phel projects.

## No Gacela Pattern

Leaf module; no Facade, Factory, or DependencyProvider. Config classes used directly by other modules' `*Config` classes via Gacela's `AbstractConfig`.

## Key Classes

### PhelConfig

Immutable `final readonly class`. Every mutation returns new instance via `with*()` methods.

**Factory**: `forProject(ProjectLayout $layout = Flat, string $mainNamespace = '')` convenience constructor.

**Public methods**:
- Directory: `withSrcDirs()`, `withTestDirs()`, `withVendorDir()`, `withFormatDirs()`
- Layout: `withLayout(ProjectLayout)` (resets src/test/format/export-from dirs per layout)
- Build: `withMainPhelNamespace()`, `withMainPhpPath()`, `withBuildDestDir()`, `withBuildConfig(PhelBuildConfig)`
- Export: `withExportNamespacePrefix()`, `withExportTargetDirectory()`, `withExportFromDirectories()`, `withExportConfig(PhelExportConfig)`
- Cache: `withCacheDir()`, `withTempDir()`, `withEnableNamespaceCache()`, `withEnableCompiledCodeCache()`, `withPhelDir()`
- Flags: `withIgnoreWhenBuilding()`, `withNoCacheWhenBuilding()`, `withKeepGeneratedTempFiles()`, `withEnableAsserts()`, `withWarnDeprecations()`, `withErrorLogFile()`
- Validation: `validate(): list<string>` (empty if valid)
- Serialization: `jsonSerialize(): array<string, mixed>`

**Deprecated (since 0.37)**: `setX()` and `useLayout()`/`useNestedLayout()`/`useFlatLayout()` shim to `with*()` counterparts (marked `#[Deprecated]`).

### PhelBuildConfig

Immutable value object. Public methods: `withMainPhelNamespace()`, `withMainPhpPath()`, `withDestDir()`. Deprecated `setX()` shims retained.

### PhelExportConfig

Immutable value object. Public methods: `withFromDirectories()`, `withNamespacePrefix()`, `withTargetDirectory()`. Deprecated `setX()` shims retained.

### ProjectLayout (enum)

- `Flat`: `src`, `tests` (default)
- `Nested`: `src/phel`, `tests/phel` (PHP under `src/php/`)
- `Root`: `.`, `.` (single-file or scratch)

## Structure

- `PhelConfig.php` (main config, ~650 LOC)
- `PhelBuildConfig.php` (build destination metadata)
- `PhelExportConfig.php` (export namespace/path mapping)
- `ProjectLayout.php` (enum: src/test directory layout)
- `PhelConfigValidator.php` (validation helper)
- `CLAUDE.md` (this file)

## Consumed By

Build, Compiler, Filesystem, Interop, Command, Formatter, Run modules.

## Dependencies

None.

## Key Constraints

- Config constants (e.g. `PhelConfig::SRC_DIRS`) are keys in Gacela's config system; never rename
- `jsonSerialize()` wire format (all three classes) is Gacela's `AbstractConfig::get()` contract; never change keys/casing
- `with*()` methods return new instances; callers must capture: `$config = $config->withX(...)`
- `withPhelDir()` honors `PHEL_DIR` env var as override for state directory (`.phel/` default)
