# Configuration

A Phel project is configured by a `phel-config.php` file at the project root
that returns a `Phel\Config\PhelConfig` object. Phel works with **zero config**:
if the file is absent, the project layout is auto-detected. Add the file when
you need to change defaults.

```php
<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

return PhelConfig::forProject(ProjectLayout::Nested)
    ->withMainPhelNamespace('my-app\main');
```

Every option has an immutable `with*()` setter that returns a new instance, so
calls chain. Inspect the effective, merged result at any time with:

```bash
phel config          # human-readable, with sources
phel config --json   # just the resolved config map
```

## Project layout

`PhelConfig::forProject(ProjectLayout $layout = Flat, string $mainNamespace = '')`
and `withLayout(ProjectLayout)` set the source/test/format/export directories in
one step. `withLayout()` resets all four directory groups to the layout's
defaults (it does not touch build, cache, or flags).

| Layout   | src dirs     | test dirs      | format dirs              | export-from   |
| -------- | ------------ | -------------- | ------------------------ | ------------- |
| `Flat`   | `src`        | `tests`        | `src`, `tests`           | `src`         |
| `Nested` | `src/phel`   | `tests/phel`   | `src/phel`, `tests/phel` | `src/phel`    |
| `Root`   | `.`          | `.`            | `.`                      | `.`           |

`Nested` suits projects that keep PHP under `src/php/` and Phel under
`src/phel/`; `Root` suits single-file or scratch projects.

## Options

All directory paths are relative to the project root.

| Method                          | Key                          | Default            | What it controls |
| ------------------------------- | ---------------------------- | ------------------ | ---------------- |
| `withSrcDirs(array)`            | `src-dirs`                   | `['src']`          | Directories scanned for Phel source namespaces (`run`, `test`, `build`). |
| `withTestDirs(array)`           | `test-dirs`                  | `['tests']`        | Directories scanned by `phel test`. |
| `withVendorDir(string)`         | `vendor-dir`                 | `'vendor'`         | Composer vendor dir used to locate Phel dependencies. |
| `withErrorLogFile(string)`      | `error-log-file`             | `'.phel/error.log'`| File runtime/compile errors are written to. |
| `withFormatDirs(array)`         | `format-dirs`                | `['src','tests']`  | Directories scanned by `phel format`. |
| `withEnableAsserts(bool)`       | `asserts-enabled`            | `true`             | Compile and run `(assert ...)` forms; when off, assertion code is stripped. |
| `withWarnDeprecations(bool)`    | `warn-deprecations`          | `false`            | Emit compiler warnings for deprecated symbols/forms. |
| `withIgnoreWhenBuilding(array)` | `ignore-when-building`       | `[]`               | Skip files during `phel build` (see below). |
| `withNoCacheWhenBuilding(array)`| `no-cache-when-building`     | `[]`               | Force recompilation of files during `phel build` (see below). |
| `withEnableNamespaceCache(bool)`| `enable-namespace-cache`     | `true`             | Cache parsed namespace metadata (see [caching](#caching)). |
| `withEnableCompiledCodeCache(bool)` | `enable-compiled-code-cache` | `true`         | Cache compiled PHP output (see [caching](#caching)). |
| `withOptimizationLevel(int)`    | `optimization-level`         | `0`                | Compiler optimization level for `build`/`run`/`test` (REPL/nREPL stay at 0). |
| `withTempDir(string)`           | `temp-dir`                   | system temp `/phel/tmp` | Transient compilation artifacts (e.g. `(load ...)`). |
| `withCacheDir(string)`          | `cache-dir`                  | `'.phel/cache'`    | Persistent cache root (overridden by `PHEL_CACHE_DIR`). |
| `withPhelDir(string)`           | `phel-dir`                   | `''`               | Relocate the whole `.phel/` state dir (overridden by `PHEL_DIR`). See [Project Layout](project-layout.md). |
| `withKeepGeneratedTempFiles(bool)` | `keep-generated-temp-files` | `false`          | Keep intermediate artifacts in the temp dir for debugging. |

### Build (`out`)

Set via flattened setters on `PhelConfig`, or by passing a `PhelBuildConfig` to
`withBuildConfig()`.

| Method                          | Nested key             | Default | What it controls |
| ------------------------------- | ---------------------- | ------- | ---------------- |
| `withMainPhelNamespace(string)` | `main-phel-namespace`  | `''`    | Entry-point namespace; empty disables entry-point generation. |
| `withBuildDestDir(string)`      | `dir`                  | `'out'` | Output directory for compiled PHP. |
| `withMainPhpPath(string)`       | `main-php-path`        | derived | Entry-point PHP file; a bare name lands under the dest dir, `.php` is appended if missing. Leave unset to derive `<destDir>/index.php`. |

### Export (`export`)

Set via flattened setters, or by passing a `PhelExportConfig` to
`withExportConfig()`.

| Method                            | Nested key         | Default              | What it controls |
| --------------------------------- | ------------------ | -------------------- | ---------------- |
| `withExportFromDirectories(array)`| `from-directories` | `['src']`            | Source dirs scanned by `phel export` for `^{:export true}` fns. |
| `withExportNamespacePrefix(string)` | `namespace-prefix` | `'PhelGenerated'`  | PHP namespace prefix for generated wrappers. |
| `withExportTargetDirectory(string)` | `target-directory` | `'src/PhelGenerated'` | Directory `phel export` writes generated PHP into. |

> **Ordering:** `withBuildConfig()` and `withExportConfig()` replace the whole
> value object, overwriting anything set via the flattened setters above (and
> resetting unspecified fields to their defaults). Call them *before* the
> flattened setters, or just use the flattened setters.

## Ignore vs. no-cache when building

Both take a list of substrings matched against a file path; they differ in
*which* path and *what* happens on a match.

| Option                 | Matched against        | Effect on a match |
| ---------------------- | ---------------------- | ----------------- |
| `ignore-when-building` | the **source** path    | file is **not compiled** and not included in the output |
| `no-cache-when-building` | the **compiled output** path | file is **recompiled on every build** (never served from cache) |

```php
return PhelConfig::forProject()
    ->withIgnoreWhenBuilding(['src/phel/local.phel'])   // never build this file
    ->withNoCacheWhenBuilding(['generated']);           // always rebuild matching output
```

## Caching

Phel keeps two independent caches under the cache dir; both are on by default.

| Flag                          | Caches                                   | Location                       |
| ----------------------------- | ---------------------------------------- | ------------------------------ |
| `enable-namespace-cache`      | parsed namespace metadata (`(ns ...)` deps) | `<cache-dir>/namespace-cache.php` |
| `enable-compiled-code-cache`  | compiled PHP output, keyed by content hash | `<cache-dir>/compiled/`        |

The compiled-code cache is also invalidated when the optimization level
changes. Disable either flag to force re-parsing / recompilation (useful when
hacking on the compiler itself). See [Performance](performance.md) for cache
reset tips and [Project Layout](project-layout.md) for the `.phel/` directory.

## Local overrides: `phel-config-local.php`

A second, optional `phel-config-local.php` at the project root is merged on top
of `phel-config.php`. Use it for per-developer or per-machine settings you do
not want to commit (`phel init` adds it to `.gitignore`). It returns a
`PhelConfig` just like the main file:

```php
<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

// Local-only: keep temp files around and warn about deprecations while developing.
return PhelConfig::forProject()
    ->withKeepGeneratedTempFiles(true)
    ->withWarnDeprecations(true);
```

## Precedence

When the same setting comes from multiple sources, the highest wins:

1. Environment variables — `PHEL_DIR`, `PHEL_CACHE_DIR`, `GACELA_CACHE_DIR`
2. `phel-config-local.php`
3. `phel-config.php`
4. `PhelConfig` constructor defaults
5. Auto-detected layout (only when no `phel-config.php` exists)

Run `phel config` to see which files were applied and the resulting values.
