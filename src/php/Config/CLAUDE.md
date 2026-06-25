# Config Module

Pure data/model layer defining configuration structure for Phel projects. Leaf module, **no Gacela pattern**: these classes are consumed directly by other modules' `*Config` classes via Gacela's `AbstractConfig`.

## Structure

| File | Role |
|------|------|
| `PhelConfig.php` | Immutable `final readonly` config model (~640 LOC); `with*()` builder API |
| `PhelBuildConfig.php` | Value object for build settings (key `out`) |
| `PhelExportConfig.php` | Value object for export settings (key `export`) |
| `ProjectLayout.php` | Backed enum: `Flat` / `Nested` / `Root` |
| `PhelConfigValidator.php` | Validates src/test/vendor dirs; backs `PhelConfig::validate()` |
| `ConfigLoadException.php` | `wrapIfConfigError()` rethrows errors originating from `phel-config.php` |

## PhelConfig API

- `forProject(ProjectLayout = Flat, string $mainNamespace = '')` — convenience constructor; sets layout, optionally main phel namespace.
- `with*()` setters — directory, layout, build, export, cache, flag mutations. Each returns a NEW instance.
- `withOptimizationLevel(int)` — key `optimization-level`, clamped `>= 0`; consumed by Build/Run (REPL/nREPL ignore it).
- `withPhelDir(string)` — state directory (`.phel/` default); honors `PHEL_DIR` env var as override.
- `validate(): list<string>` — empty list if valid.
- `jsonSerialize(): array`.

PhelBuildConfig: `withMainPhelNamespace` / `withMainPhpPath` / `withDestDir`.
PhelExportConfig: `withFromDirectories` / `withNamespacePrefix` / `withTargetDirectory`.

## ProjectLayout

| Case | src / test dirs |
|------|-----------------|
| `Flat` (default) | `src`, `tests` |
| `Nested` | `src/phel`, `tests/phel` (PHP lives under `src/php/`) |
| `Root` | `.`, `.` (single-file / scratch) |

`withLayout(ProjectLayout)` resets src/test/format/export-from dirs per layout.

## Key Constraints

- Config constants (e.g. `PhelConfig::SRC_DIRS = 'src-dirs'`) are Gacela config keys — never rename.
- `jsonSerialize()` wire keys/casing (all three classes) are Gacela's `AbstractConfig::get()` contract — never change.
- `with*()` returns a new instance; callers MUST capture: `$config = $config->withX(...)`.
- **Removed (breaking, deprecated since 0.37):** `setX()` setters, `useLayout()`/`useNestedLayout()`/`useFlatLayout()` aliases, and the `PhelConfigDeprecations` trait. Use `with*()` instead.
