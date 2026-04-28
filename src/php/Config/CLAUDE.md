# Config Module

Pure data/model layer defining the configuration structure for Phel projects.

## No Gacela Pattern

This module does **not** follow Gacela internally — no Facade, Factory, or DependencyProvider. Config classes are used directly by other modules' `*Config` classes via Gacela's `AbstractConfig`.

## Key Classes

### `PhelConfig` (main)
Factory: `PhelConfig::forProject(?string $mainNamespace)` — creates config with optional namespace.

**Directory config**: `setSrcDirs()`, `setTestDirs()`, `setVendorDir()`, `setFormatDirs()`
**Build config**: `setBuildConfig(PhelBuildConfig)`, `setMainPhelNamespace()`, `setBuildDestDir()`
**Export config**: `setExportConfig(PhelExportConfig)`, `setExportNamespacePrefix()`, `setExportTargetDirectory()`
**Cache**: `setCacheDir()`, `setTempDir()`, `setEnableNamespaceCache()`, `setEnableCompiledCodeCache()`
**Other**: `setIgnoreWhenBuilding()`, `setNoCacheWhenBuilding()`, `setKeepGeneratedTempFiles()`, `setEnableAsserts()`, `setWarnDeprecations()`
**Validation**: `validate(): array` — returns list of errors
**Serialization**: `jsonSerialize(): array` — implements `JsonSerializable`

### `PhelBuildConfig`
Build-specific: `setMainPhelNamespace()`, `setMainPhpPath()`, `setDestDir()`, `shouldCreateEntryPointPhpFile()`

### `PhelExportConfig`
Export/interop: `setFromDirectories()`, `setNamespacePrefix()`, `setTargetDirectory()`

### `ProjectLayout` (enum)
- `Flat` — `src`, `tests` (default)
- `Nested` — `src/phel`, `tests/phel` (useful when PHP lives under `src/php/`)
- `Root` — `.`, `.` (single-file / scratch)

## Consumed By

Build, Compiler, Filesystem, Interop, Command, Formatter, Run — all read `PhelConfig` constants via their Gacela `*Config` classes.

## Dependencies

None. This is a leaf module with zero internal dependencies.

## Key Constraints

- Config constants (e.g. `PhelConfig::SRC_DIRS`) are used as keys throughout Gacela's config system
- Default source dirs: `['src/phel']`, test dirs: `['tests/phel']` (changed by `useLayout`)
- Auto-detection in `Phel.php` checks for nested (`src/phel`) vs flat (`src`) layout when no `phel-config.php` exists
