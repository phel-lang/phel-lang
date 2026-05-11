# Config Module

Pure data/model layer defining the configuration structure for Phel projects.

## No Gacela Pattern

This module does **not** follow Gacela internally — no Facade, Factory, or DependencyProvider. Config classes are used directly by other modules' `*Config` classes via Gacela's `AbstractConfig`.

## Key Classes

### `PhelConfig` (main)

Immutable `final readonly class`. Every mutation returns a new instance.

Factory: `PhelConfig::forProject(string $mainNamespace = '', ?ProjectLayout $layout = null)` — creates config with optional namespace; auto-detects layout from cwd when omitted.

**Directory**: `withSrcDirs()`, `withTestDirs()`, `withVendorDir()`, `withFormatDirs()`
**Layout**: `withLayout(ProjectLayout)`
**Build (flat on root)**: `withMainPhelNamespace()`, `withMainPhpPath()`, `withBuildDestDir()`, `withBuildConfig(PhelBuildConfig)` (escape hatch)
**Export (flat on root)**: `withExportNamespacePrefix()`, `withExportTargetDirectory()`, `withExportFromDirectories()`, `withExportConfig(PhelExportConfig)` (escape hatch)
**Cache**: `withCacheDir()`, `withTempDir()`, `withEnableNamespaceCache()`, `withEnableCompiledCodeCache()`, `withPhelDir()`
**Other**: `withIgnoreWhenBuilding()`, `withNoCacheWhenBuilding()`, `withKeepGeneratedTempFiles()`, `withEnableAsserts()`, `withWarnDeprecations()`, `withErrorLogFile()`
**Validation**: `validate(): array` — returns list of errors
**Serialization**: `jsonSerialize(): array` — implements `JsonSerializable`

**Deprecated (since 0.37, removed in future major)**: every `setX()` and `useLayout()`/`useNestedLayout()`/`useFlatLayout()` shim to its `withX()` / `withLayout()` counterpart. Annotated with `#[Deprecated]`.

### `PhelBuildConfig`

Immutable value object. Constructor accepts named args: `mainPhelNamespace`, `mainPhpPath`, `destDir`. Use `withMainPhelNamespace()`, `withMainPhpPath()`, `withDestDir()` for chained updates. Setters retained as `#[Deprecated]` shims.

### `PhelExportConfig`

Immutable value object. Constructor accepts named args: `fromDirectories`, `namespacePrefix`, `targetDirectory`. `withFromDirectories()`, `withNamespacePrefix()`, `withTargetDirectory()` for updates. Setters retained as `#[Deprecated]` shims.

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
- `jsonSerialize()` wire shape on all three classes is the contract with Gacela's `AbstractConfig::get()` — never change keys/casing
- Auto-detection in `Phel.php` checks for nested (`src/phel`) vs flat (`src`) layout when no `phel-config.php` exists
- Every `with*()` returns a new instance; callers must capture the return value (`$config = $config->withX(...)`), never call for side effect
