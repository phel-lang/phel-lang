---
name: module-config
description: 'Load when editing src/php/Config: the PhelConfig data model for phel-config.php.'
scope: src/php/Config
---

# Config Module

Configuration data model for `phel-config.php`. Shared kernel, **No Gacela Pattern**: other modules' `*Config` classes read it through Gacela's `AbstractConfig`.

## Key Constraints

- Config constants (`PhelConfig::SRC_DIRS = 'src-dirs'`) are Gacela config keys; never rename.
- `jsonSerialize()` wire keys and casing (`PhelConfig`, `PhelBuildConfig`, `PhelExportConfig`) are the `AbstractConfig::get()` contract; never change.
- `PhelConfig` is `final readonly`. `with*()` returns a new instance; callers MUST capture it: `$config = $config->withX(...)`. The old `setX()` setters and `use*Layout()` aliases are gone.
- `withLayout(ProjectLayout)` resets src, test, format and export-from dirs per layout: `Flat` (default) `src`/`tests`, `Nested` `src/phel`/`tests/phel`, `Root` `.`/`.`.
- `withOptimizationLevel(int)` clamps to `>= 0`; REPL and nREPL ignore it.
- `withPhelDir(string)`: state directory, `.phel/` by default; the `PHEL_DIR` env var overrides it.
- `withAppModulePaths(list<string>)` scopes Gacela's module discovery, which otherwise `class_exists()`-walks the whole project root and fatals on any file that cannot load standalone (under `tests/`, say). `Phel::bootstrap()` reads it before the merged config exists, so it is parsed straight from `phel-config.php`, not via `AbstractConfig::get()`.
- `PhelConfigValidator` backs `validate()` (empty list when valid). Its relative-dirs rule is advisory: only `Run\Domain\Config\ConfigDiagnostics` reports it, and nothing blocks on it.
- `StrictPhpConfigReader` rejects a `phel-config.php` returning `null` instead of coercing it to `[]`; wired in `Phel::configFn()`. `ConfigLoadException::wrapIfConfigError()` rethrows errors that originate in `phel-config.php`.
