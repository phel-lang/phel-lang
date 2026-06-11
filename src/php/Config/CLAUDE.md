# Config Module

Pure data/model layer defining configuration structure for Phel projects. No Gacela pattern: leaf module; config classes are used directly by other modules' `*Config` classes via Gacela's `AbstractConfig`.

## Key Classes

### PhelConfig

Immutable `final readonly class` (~620 LOC). Every mutation returns a new instance via `with*()` methods (directory, layout, build, export, cache, flag setters); `withOptimizationLevel(int)` (key `optimization-level`, clamped >= 0) sets the compiler optimization level consumed by Build/Run (REPL/nREPL ignore it); `forProject(ProjectLayout = Flat, string $mainNamespace = '')` convenience constructor; `validate(): list<string>` (empty if valid); `jsonSerialize()`.

**Deprecated (since 0.37)**: `setX()` and `useLayout()`/`useNestedLayout()`/`useFlatLayout()` shim to `with*()` counterparts (marked `#[Deprecated]`), kept in the `PhelConfigDeprecations` trait so they do not clutter the canonical API. Permanent backward-compatibility aliases, not scheduled for removal in a minor release; removal would require a major version bump.

### PhelBuildConfig / PhelExportConfig

Immutable value objects (`withMainPhelNamespace`/`withMainPhpPath`/`withDestDir`; `withFromDirectories`/`withNamespacePrefix`/`withTargetDirectory`). Deprecated `setX()` shims retained.

### ProjectLayout (enum)

- `Flat`: `src`, `tests` (default)
- `Nested`: `src/phel`, `tests/phel` (PHP under `src/php/`)
- `Root`: `.`, `.` (single-file or scratch)

`withLayout(ProjectLayout)` resets src/test/format/export-from dirs per layout.

## Key Constraints

- Config constants (e.g. `PhelConfig::SRC_DIRS`) are keys in Gacela's config system; never rename
- `jsonSerialize()` wire format (all three classes) is Gacela's `AbstractConfig::get()` contract; never change keys/casing
- `with*()` methods return new instances; callers must capture: `$config = $config->withX(...)`
- `withPhelDir()` honors `PHEL_DIR` env var as override for state directory (`.phel/` default)
