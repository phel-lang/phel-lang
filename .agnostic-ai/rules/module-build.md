---
name: module-build
description: 'Load when changing the Build module: namespace extraction, dependency order, compilation, caches.'
scope: src/php/Build
---

# Build Module

Namespace extraction, dependency ordering, compilation, caching.

## Facade notes

- `unresolvedRequireError(...)`: error for a require with no source file, null when `BundledNamespaceIndex` resolves it. The emitted `ns` form (`NsEmitter`) throws it on its miss path.
- `flushCompiledCodeCache()`: a parent calls it before spawning workers.
- `refreshNamespaceScans()`: drops process-local scans and the namespace restoration memo for daemon analysis; disk caches stay.
- `clearCache()`: clears `<tempDir>` and `<cacheDir>`, empties `<phelDir>/opcache` in place.
- `enableBuildMode()` / `disableBuildMode()` / `isBuildMode()` are static and write `*build-mode*` straight to `Registry`, avoiding `Phel::__callStatic` on the hot `(load ...)` path.
- `enterDependencyLoad()` / `leaveDependencyLoad(bool)` / `isLoadingDependencies()` are static and flag the region where an `ns` form evaluates its required files (`BuildConstants::LOADING_DEPENDENCIES`). This is not build mode.

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `CompilerFacadeInterface::class` | Phel-to-PHP compilation |
| `CommandFacadeInterface::class` | output and source dirs, error formatting |

Non-facade edge, **Config**: `BuildConfig` and `Domain/Compile/Output/EntryPointPhpFile` read `PhelConfig` / `PhelBuildConfig`.

## Namespace extraction errors

- First form not `ns`/`in-ns` and fails on an unresolved symbol: asking for that file throws `Shared\Exceptions\MissingNsFormException`; `phel run <file>` still gets the hint.
- Rejected namespace analysis restores the compiler environment, so a failed saved declaration cannot replace aliases before source analysis starts.
- An `ns` form the analyzer rejects (`(:require [x :refer :all])`) throws `CompilerException` with its snippet. `ProjectCompiler` passes `failOnInvalidNsForm: true`, so `build` fails naming the file.
- A directory scan (`NamespaceExtractor`, `CachedNamespaceExtractor`) skips both cases and unparseable files: one stray `.cljc` must not stop `eval`, `run`, `lint` or the REPL. The scan index stamps a skipped file's mtime, so it is reread only after a change.

## Dependency resolution

- Canonicalise seeds, index keys and declared dependencies with `Munge::canonicalNs`, so either separator resolves (`Watch` passes the backslash form).
- Throw `ExtractorException` when a `(:require ...)` names nothing loadable: no source file, no `clojure.*` remap target (`FrameworkNamespaces::clojureTarget`; `clojure.set` is `phel.core`) in the scan, not in the registry, and no `phel.*` namespace `BundledNamespaceIndex` finds on the source and vendor dirs (a vendored scan can lack the stdlib). The message suggests the closest namespace.
- `MissingRequireReporter` rereads the requiring `ns` form and throws `CompilerException` wrapping `MissingNamespaceException` (`PHEL014`), caret on the required namespace, searched dirs as note. Without a readable requiring file (REPL, eval), throw a bare `ExtractorException`.
- A `clojure.*` seed walks its `phel.*` target. Other unresolved seeds are tolerated (the REPL checks the empty result).
- `DependenciesForNamespace` memoizes per `(dirs, seeds)` per process.

## Version stamping

- `EntryPointPhpFile` emits `\Phel::assertBuiltWith(...)` after Composer autoloading and before bootstrap, then `\Phel::setupRuntimeArgs(...)`.
- Built files and exported wrappers carry `VersionResolver::current()` (dev commit suffix included) and refuse another runtime version. Generated calls to internals are no cross-version ABI.
- `BuildConfig::getLockedPhelVersion()` / `getLockedPhelReference()` read the Composer lock so `BuildCommand` warns on a different Phel. Dev dependencies compare by source or dist commit, not branch; an unknown reference is no mismatch.

## Portable build sources

- `ProjectCompiler` plans every primary and secondary source copy (`ArtifactSourcePaths`), then rewrites completed artifacts after harvesting. Match source-path slots, not arbitrary strings. Preserve target mtimes for incremental reuse and invalidate rewritten OPcache entries.
- Outside a build, `ArtifactSourcePath::resolve()` names the shipped `.phel` copy beside the compiled PHP. While compiling, a scoped map restores original paths when a warm artifact supplies `*file*`, definition metadata or magic constants; restore the previous map in `finally`.
- `SourcePathNode` keeps bare `__FILE__` / `__DIR__` out of literal folding. Compiler FILE/CACHE output carries `SourcePathMarker` comments for the artifact pass; statement evaluation still gets the original value.
- Harvested secondaries (`SecondaryFileHarvester`) use `BuiltFilePreamble` and sibling `.php.map` / `.phel` files, like primaries. They come from the compiled-code cache, else `CompiledSecondaryStore`, so a cache-off build still emits them. `ArtifactSourceMap` rebases generated map columns after token replacements without changing source coordinates or line counts.
- Rationale: `docs/adr/0023-build-source-paths-follow-the-artifact.md`.

## Compiled-code cache

- `CompiledCodeCache` keys on `CompiledSourceHash::of(source, optimizationLevel, envFingerprint)` under an index stamped with the running Phel version. `SourceFilePath` canonicalizes parent dirs for index keys and emitted source paths but keeps file-symlink identity (source-relative loads can differ). Bump `CacheIndexFile::INDEX_FORMAT_VERSION` when entry shape or keys change.
- An entry stores its compile's deprecation notices; `FileEvaluator` replays them on every hit via `CompilerFacadeInterface::replayDeprecations()`, so `--warn-deprecations` matches warm and cold. Never key the cache on the flag. `NamespaceExtractor` reads `ns` forms inside `withoutDeprecations()`.
- `put`/`invalidate` mutate the in-memory index only; it flushes once per process at shutdown (`DeferredFlushTrait`), keeping cold-build index I/O O(N). `CacheIndexFile::save()` does atomic write, `flock` and disk merge, so concurrent test workers lose no entries. Compiled `.php` files and `clear()` write eagerly.
- Test gotcha: a test needing cross-instance disk persistence in one process must call `save()`.
- `put` over a file this process `require`d keeps its old header in `Shared\SourceMap\SupersededSourceMaps`, and `get` stops serving that path, so one path never runs two versions. `FileEvaluator` caches `getCodeWithSourceMap()` so cached errors map to `.phel`.
- `FileEvaluator` is a singleton so repeated `(load ...)` calls keep the index.

## Scan-index and namespace caches

- `PhpScanIndexCache` (`<cacheDir>/scan-index.php`, `NullScanIndexCache` when disabled) lets `CachedNamespaceExtractor` skip the directory walk across processes. Keyed by resolved dir set; served only if all hold:
  - every discovered, non-pruned directory's `mtime` matches, empty nested ones included;
  - every file's `mtime` matches;
  - every directory and file `mtime` is older than the entry's `recordedAt`;
  - `ScanIndexEntry::covers()` the requested dirs that exist now (only this catches a dir that appears after the scan).
- Directory stamps are captured before descent. The active cache subtree is excluded unless it equals or contains a source, test or vendor scan root (resolved paths).
- Both caches write through `LockedPhpCacheWriter`. Prune unreachable entries in `save()`, after the disk merge, before the write. Unreachable means the anchor path is gone, not stale. Otherwise the per-call `.phel_temp_<uniqid>` dir `Api\Infrastructure\PhelFunctionRuntimeLoader` scans piles up. Never prune at load: `isValid()` already rejects dead entries.
- Never serve racily clean entries: `filemtime` is whole-second. `NamespaceCacheEntry` and `ScanIndexEntry` take `recordedAt` before the read or walk; a file not older than it is reread. Tests expecting a warm hit must backdate files and dirs. `ProjectCompiler` does the same: a source not older than its compile start gets target mtime 0, forcing a recompile.

## OPcache file cache

- `bin/phel` re-execs with `opcache.file_cache=<phel-dir>/opcache` (`PhelProjectDirectory::opcachePath()`; ignores configured `phel-dir` because the CLI resolves it before config loads). It prunes on start, in the re-exec'd child only: foreign system-id subtrees and orphaned `.bin` files. The system id is probed (`OpcacheFileCache::detectSystemId()`); userland cannot compute it.
- `CacheClearer` empties it in place via `OpcacheFileCachePruner::clearContents()`, never `deleteDirectory()`: PHP aborts at startup when `opcache.file_cache` points at a missing path.
- `opcache_invalidate()` cannot drop a deleted file's `.bin` under `opcache.file_cache_only=1` (the CLI's mode): delete the `.bin`.

## Compilation order and invalidation

- `DependencyTracker` stores each file's source and owning namespace; graph writes defer like the index. Namespace invalidation completes the transitive cascade before returning (visited sets for cycles). Do not cascade on every cold miss: core `(load ...)` fragments share `phel.core`, so that evicts valid siblings.
- `ProjectCompiler` relies on `TopologicalNamespaceSorter` order: it force-recompiles any dependent of a namespace recompiled this run (`dependsOnRecompiled`), even with unchanged mtime. One pass is transitive.
- Main namespace auto-detect: `core.phel` or `main.phel` in source dirs (`BuildConfig`).
- Exclude the output dir from extraction (namespace shadowing).
- Prune Phel's own `src/php` from every scan (`ExcludedScanPaths`, even `none()`): its ~1,200 PHP files slow warm runs.
- Prune `<scan root>/<vendor-dir>/` like `vendor/`: a root-layout scan would otherwise pick up `phel-lang/phel-lang`'s stdlib over the PHAR's bundled one. Vendor libraries are scanned as their own roots.

## Build markers in `<out>`

The incremental check is mtime-only, so `ProjectCompiler` writes a marker per setting and force-recompiles when it changes. The default value leaves no marker: `.phel-optimization-level`, `.phel-cache-env-fingerprint`, `.phel-strip-symbol-meta`.

## Settings

- Optimization level (`BuildConfig::getOptimizationLevel()`): `FileCompiler` constructor default, a per-call override wins (`phel build -O`). `FileEvaluator` mixes it into the cache hash when > 0.
- `cache-env-vars` (`PhelConfig::withCacheEnvVars`): env vars joining the compiled-code cache key, because a macro reading `getenv` bakes the value into PHP. `EnvCacheFingerprint::of()` hashes sorted, deduplicated name + `md5(value)` pairs (unset is `-`). `BuildFactory::cacheEnvFingerprint()` feeds `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader). Values are never stored.
  - Project-wide: flipping a value invalidates every entry, overwritten in place (`CachePathResolver::compiledPath()` keys on namespace + path). Alternating values want a `cache-dir` each.
  - Declaring nothing leaves the hash byte-identical.
  - Keep the marker: without it the reuse path `require_once`s the old primary, whose build-mode `(load ...)` misses its secondary and recompiles against a half-registered registry (`Cannot resolve symbol 'map'`).
  - Not covered: the precompiled-sibling fast path and `Lint`'s diagnostic cache.
- `strip-symbol-meta`: `FileCompiler` and `SecondaryFileHarvester` strip def metadata (`SymbolMetaStripper`) from written artifacts only; build-time evaluation keeps full meta. Stripped builds drop source maps. Keep the marker: a stripped target must never be reused as compile cache.

## Precompiled-sibling fast path

- Before the cache, `FileEvaluator::evalFile` `require_once`s a `BuiltFilePreamble`-marked `<name>.php` next to the source. Use `require_once`, not `require`: a second load must not re-null forward-declared defs.
- Reuse it only when its version stamp matches; otherwise compile. `BuiltFilePreamble::isPresent()` stays tolerant for source-map recognition.
- The PHAR ships `phel.core` this way (`build/build-phar.php::addPrecompiledStdlibSiblings`). Bundle a namespace only with its full transitive `(:require ...)` closure (FILE-mode output `require_once`s dependency siblings).
- Disabled, with the emitted `(load ...)` `.php` preference, under `*build-mode*`, so `phel build` recompiles and harvests the stdlib. `PhelSourceLoader::load` keeps an outer build mode across every `(load ...)`.

## `--timing` and `--report`

- `--timing` installs `PhaseTimingProfilerHook` via `Registry::setProfilerHook()` around `compileProject`, reset in `finally`. `wrapFn()` is a deliberate no-op: a build evaluates `def`, so proxies would be baked into output. Pair with `--no-cache` to compare.
- `BuildReport::fromCompiledFiles` is a pure VO; `BuildCommand` renders it.
