---
name: module-build
description: 'Load when changing the Build module: namespace extraction, dependency order, compilation, caches.'
scope: src/php/Build
---

# Build Module

Namespace extraction, dependency ordering, compilation, caching.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)` | Extract `NamespaceInformation` (VO in `Phel\Shared`) |
| `getDependenciesForNamespace(array $dirs, array $ns)` | Topologically sorted dependencies |
| `unresolvedRequireError(required, requiring, requiringFile, searchedDirs)` | Error for a require with no source file; null when `BundledNamespaceIndex` resolves it. The emitted `ns` form throws the result on its miss path |
| `compileFile(src, dest)` / `evalFile(src)` | Compile to PHP and write; `evalFile` skips the write |
| `flushCompiledCodeCache()` | Flush the compiled-code index and dependency graph; a parent calls it before spawning workers |
| `compileProject(Shared\BuildOptions)` | `CompiledFile[]` |
| `clearCache()` | `string[]` paths cleared from temp, cache and OPcache dirs |
| `getHealthCheck()` | Cache, output, source dir checks |
| `enableBuildMode()` / `disableBuildMode()` / `isBuildMode()` | Static; toggle `*build-mode*` by direct `Registry` write (avoids `Phel::__callStatic` on the hot `(load ...)` path) |
| `enterDependencyLoad()` / `leaveDependencyLoad(bool)` / `isLoadingDependencies()` | Static; flag the region where an `ns` form evaluates its required files (`BuildConstants::LOADING_DEPENDENCIES`, not build mode) |
| `writeLocatedException` / `writeStackTrace` / `getOutputDirectory` | Delegate to Command facade |

### Namespace extraction errors

- First form not `ns`/`in-ns` and fails on an unresolved symbol: asking for that file throws `Shared\Exceptions\MissingNsFormException`; `phel run <file>` still gets the hint.
- An `ns` form the analyzer rejects (`(:require [x :refer :all])`) throws `Shared\Exceptions\CompilerException` with its snippet. `ProjectCompiler` passes `failOnInvalidNsForm: true`, so `build` fails naming the file (#3457).
- A directory scan (`NamespaceExtractor`, `CachedNamespaceExtractor`) skips both cases and unparseable files: one stray `.cljc` or bad file must not stop `phel eval`, `run`, `lint` or the REPL (#3484). The scan index stamps a skipped file's mtime, so it is reread only after a change (#3629).

### Dependency resolution

- Canonicalise seeds, index keys and declared dependencies with `Munge::canonicalNs`, so either namespace separator resolves (`Watch` passes the backslash form).
- Throw `ExtractorException` when a `(:require ...)` names nothing loadable: no source file, no `clojure.*` remap target (`FrameworkNamespaces::clojureTarget`; `clojure.set` is `phel.core`) in the scan, not in the registry, and no `phel.*` namespace `BundledNamespaceIndex` finds on the configured source and vendor dirs (a vendored scan can lack the stdlib). The message suggests the closest namespace (`phel.strng` -> `phel.string`).
- `MissingRequireReporter` rereads the requiring `ns` form and throws `CompilerException` wrapping `MissingNamespaceException` (`PHEL014`), caret on the required namespace, searched dirs as note. Without a readable requiring file (REPL, eval), throw a bare `ExtractorException`.
- A `clojure.*` seed walks its `phel.*` target. Other unresolved *seeds* are tolerated (the REPL checks the empty result).

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `CompilerFacadeInterface::class` | Phel-to-PHP compilation |
| `CommandFacadeInterface::class` | Output/source dirs, error formatting |

Both are injected as Shared `*FacadeInterface`. One non-facade edge, **Config**: `BuildConfig` and `Domain/Compile/Output/EntryPointPhpFile` read `PhelConfig`/`PhelBuildConfig`.

### Version stamping

- `EntryPointPhpFile` emits `\Phel::assertBuiltWith(...)` after Composer autoloading and before bootstrap, then `\Phel::setupRuntimeArgs(...)`.
- Built files and exported wrappers carry `VersionResolver::current()` (dev commit suffix included) and refuse another runtime version. Rebuild after any Phel version change; generated calls to internals are no cross-version ABI.
- `BuildConfig::getLockedPhelVersion()` / `getLockedPhelReference()` read the Composer lock so `BuildCommand` warns on a different Phel. Dev dependencies compare by source or dist commit, not branch; an unknown reference is no mismatch.

## Structure

| Path | Role |
|------|------|
| `Application/ProjectCompiler` | Project build, cache invalidation cascade |
| `Application/FileEvaluator` | Eval one file, precompiled-sibling fast path |
| `Application/FileCompiler` | Compile one file to PHP |
| `Application/DependenciesForNamespace` | Per-process memoized dependency resolution |
| `Application/CachedNamespaceExtractor` | Skips dir walk via scan index |
| `Application/CacheClearer` | Clears `<tempDir>` + `<cacheDir>`; empties `<phelDir>/opcache` in place |
| `Domain/Extractor/TopologicalNamespaceSorter` | Dependency-order compilation |
| `Domain/Compile/BuildReport` + `BuildReportEntry` | `--report` VO |
| `Domain/Compile/PhaseTimingReport` | `--timing` report |
| `Domain/Compile/SecondaryFileHarvester` | Writes `(in-ns ...)` secondary `.php` siblings to the output tree, from the compiled-code cache, else `CompiledSecondaryStore` (a cache-off build still emits them) |
| `Domain/Compile/SymbolMetaStripper` | Token-based removal of `\Phel::locationMeta(...)` args when `strip-symbol-meta` is on (write path only) |
| `Domain/Compile/CompiledSecondaryStore` | In-memory hand-off of secondaries from `FileEvaluator` to `SecondaryFileHarvester` when the cache is off |
| `Infrastructure/Cache/CompiledCodeCache` | Compiled-code cache policy |
| `Infrastructure/Cache/EnvCacheFingerprint` | Hash of `cache-env-vars`, mixed into the cache key |
| `Infrastructure/Cache/PhpScanIndexCache` | Persisted dir-scan index |
| `Domain/Cache/NullScanIndexCache` | No-op scan index (in `Domain` so `Application` never imports outward) |
| `Infrastructure/Cache/PhpNamespaceCache` / `NullNamespaceCache` | Namespace-extraction cache |
| `Infrastructure/Cache/LockedPhpCacheWriter` | Flock'd `var_export` write shared by `PhpNamespaceCache` + `PhpScanIndexCache` |
| `Infrastructure/Timing/PhaseTimingProfilerHook` | `--timing` hook |
| `Infrastructure/Command/BuildCommand` / `CacheClearCommand` | CLI |

## Key Constraints

### Caching (namespace extraction and compiled code, each optional)

- **Compiled-code cache** (`CompiledCodeCache`): keys entries by `CompiledSourceHash::of(source, optimizationLevel, envFingerprint)` under an index stamped with the running Phel version (beta commits included). Delegates to `CacheDirectory`, `CacheIndexFile` (load/save/merge), `NamespaceEnvironmentStore`, `CachePathResolver`, `AtomicFileWriter`.
- An entry stores its compile's deprecation notices (`EmitterResult::getDeprecations()`, index field `deprecations`); `FileEvaluator` replays them on every hit via `CompilerFacadeInterface::replayDeprecations()`, so `--warn-deprecations` matches warm and cold (#3222). Never key the cache on the flag. `NamespaceExtractor` reads `ns` forms inside `withoutDeprecations()` (#3381). `INDEX_FORMAT_VERSION` 1.10 rejects older namespace environments lacking default PHP-import provenance (#3718).
- `put`/`invalidate` mutate the in-memory index only; it flushes **once per process at shutdown** (`DeferredFlushTrait`), keeping cold-build index I/O O(N). `CacheIndexFile::save()` does atomic write + `flock` + disk merge, so concurrent `phel test` workers lose no entries. Compiled `.php` files are written eagerly (`AtomicFileWriter`). `clear()` writes the empty index eagerly.
- **Test gotcha:** a test needing cross-instance disk persistence in one process must call `save()`.
- **Scan-index cache** (`PhpScanIndexCache`, `<cacheDir>/scan-index.php`, `ScanIndexCacheInterface`; `NullScanIndexCache` when disabled) lets `CachedNamespaceExtractor` skip the directory walk across processes. Keyed by resolved dir-set; served only if all hold:
  - every discovered, non-pruned directory's `mtime` matches, including empty nested directories;
  - per-file `mtime` matches;
  - every directory and file `mtime` is older than the entry's `recordedAt`;
  - `ScanIndexEntry::covers()` the requested directories that exist now (only this catches a configured dir that appears after the scan, #3205).

  Directory stamps are captured before descent. Format 1.2 rejects older root-only fingerprints. The active cache subtree is excluded unless it equals or contains a configured source, test or vendor scan root (resolved paths, symlinks included). Persistence works like `PhpNamespaceCache`. Injected via `BuildFactory::createScanIndexCache()`; path from `BuildConfig::getScanIndexCacheFile()`; cleared by `CacheClearer`.
- **Prune unreachable entries in `save()`** (namespace and scan-index caches), after the disk merge, before the write. Unreachable means the anchor path is *gone* (`ScanIndexEntry::isReachable()` probes `perDir` keys; `PhpNamespaceCache` the file key), not out of date. Else the merge keeps dead entries, e.g. the unique `.phel_temp_<uniqid>` dir `Api\Infrastructure\PhelFunctionRuntimeLoader` scans per `phel doc` / LSP hover / REPL completion (#3007). Never prune at load: `isValid()` already rejects dead entries.
- **Never serve racily clean entries.** `filemtime` is whole-second. `NamespaceCacheEntry` and `ScanIndexEntry` carry `recordedAt`, taken *before* the read or walk; a file with mtime not older than it is reread (#3537). Scan-index directory stamps follow the same rule. Tests expecting a warm hit must backdate files and directories. `ProjectCompiler`'s incremental reuse (target mtime equals source mtime) does the same: a source not older than its compile start gets target mtime 0, forcing a recompile (#3564).
- `DependenciesForNamespace` memoizes per `(dirs, seeds)` per process for its three root callers (`FileRunner`, `DataReadersLoader`, `NamespaceLoader`).

### OPcache file cache

- `bin/phel` re-execs with `opcache.file_cache=<phel-dir>/opcache` (`PhelProjectDirectory::opcachePath()`; ignores configured `phel-dir`: the CLI resolves it before config loads). Prune it or it grows without bound (#3268).
- `CacheClearer` empties it **in place** via `OpcacheFileCachePruner::clearContents()`, never `deleteDirectory()`: PHP aborts at startup when `opcache.file_cache` points at a missing path.
- `bin/phel` prunes on start, in the re-exec'd child only: foreign system-id subtrees and orphaned `.bin` files below the system temp root. The system id is probed (`OpcacheFileCache::detectSystemId()` reads the running script's `.bin`); userland cannot compute it.
- `opcache_invalidate()` cannot drop a deleted file's `.bin` under `opcache.file_cache_only=1` (the CLI's mode): delete the `.bin`.

### Compilation order and invalidation

- `DependencyTracker` stores each file's source and owning namespace. Graph writes defer to shutdown (`DeferredFlushTrait`) or explicit `save()`; `LockedPhpCacheWriter` locks while atomically merging disk and in-memory edges. Namespace invalidation completes the transitive cascade before returning, with visited sets for cycles. Do not cascade on every cold miss: core `(load ...)` fragments share `phel.core`, so that evicts valid siblings.
- `ProjectCompiler` relies on `TopologicalNamespaceSorter` order: it force-recompiles any dependent whose `getDependencies()` includes a namespace recompiled this run (`dependsOnRecompiled`), even with unchanged mtime. One pass is transitive, so a changed macro never leaves a stale expansion.
- Main namespace auto-detect: `core.phel` or `main.phel` in source dirs.
- Exclude the output dir from extraction (prevents namespace shadowing).
- Prune Phel's own `src/php` from every scan (`ExcludedScanPaths`, even `none()`): recounting its ~1,200 PHP files slows warm runs (#3467).

### Build markers in `<out>`

`phel build`'s incremental check is mtime-only, so `ProjectCompiler` writes a marker per setting and force-recompiles when it changes. The default value leaves no marker.

| Marker | Setting |
|--------|---------|
| `.phel-optimization-level` (`OPTIMIZATION_LEVEL_FILE`) | optimization level (0 = none) |
| `.phel-cache-env-fingerprint` | `cache-env-vars` fingerprint (empty = none) |
| `.phel-strip-symbol-meta` | `strip-symbol-meta` |

### Optimization level

- `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) is injected into `FileCompiler` (constructor default; a per-call override wins, used by `phel build -O`) and `FileEvaluator` (mixed into the cache hash when > 0).

### cache-env-vars

- `PhelConfig::withCacheEnvVars(['MY_MODE'])` (key `cache-env-vars`, default none) names env vars that join the compiled-code cache key (`(php/getenv "MY_MODE")` in a macro bakes the value into PHP while the key sees only source, #3236). `EnvCacheFingerprint::of()` hashes sorted, deduplicated name + `md5(value)` pairs (unset is `-`, not empty). `BuildFactory::cacheEnvFingerprint()` injects it into `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader), both mixing it into `CompiledSourceHash::of()`. Values are hashed, never stored.
- The fingerprint is project-wide: flipping a value invalidates every entry, overwritten in place (`CachePathResolver::compiledPath()` keys on namespace + source *path*). Alternating values want a `cache-dir` each.
- Declaring nothing leaves the hash byte-identical; no `INDEX_FORMAT_VERSION` bump needed.
- Keep the fingerprint marker: without it the reuse path `require_once`s the old primary, whose build-mode `(load ...)` misses its secondary, which recompiles against a half-registered registry (`Cannot resolve symbol 'map'`).
- Not covered: the precompiled-sibling fast path and `Lint`'s diagnostic cache.

### strip-symbol-meta

- `PhelConfig::withStripSymbolMeta()` (key `strip-symbol-meta`, default off) makes `FileCompiler` and `SecondaryFileHarvester` strip def metadata from written artifacts. Write path only: build-time evaluation keeps full meta for inference/arity data.
- Stripped builds also drop source maps (line numbers shift). Keep the marker: a stripped target must never be reused as compile cache (its `require_once` would register meta-less defs).

### Source maps

- `FileEvaluator` caches `getCodeWithSourceMap()`, so errors from cached namespaces map to `.phel` via the `// `/`// ;;` header comments. `CompiledCodeCache::put` over a file this process `require`d keeps its old header in `Shared\SourceMap\SupersededSourceMaps` (memory only), and `get` stops serving that path for the process, so one path never runs two versions.

### Precompiled-sibling fast path

- Before the cache, `FileEvaluator::evalFile` `require_once`s a `BuiltFilePreamble`-marked `<name>.php` next to the `.phel`/`.cljc` source and returns. Use `require_once`, not `require`: a second load must not re-run the primary and re-null its forward-declared defs (#2673).
- Reuse the sibling only when its version stamp matches the running Phel version; an older or unstamped one falls back to compiling. `BuiltFilePreamble::isPresent()` stays tolerant for source-map recognition.
- The PHAR ships `phel.core` this way (`build/build-phar.php::addPrecompiledStdlibSiblings`); running it populates the registry (defs + macro meta), enough for the analyzer.
- Bundle a namespace only with its full transitive `(:require ...)` closure (FILE-mode output `require_once`s dependency siblings directly). `phel.core` is self-contained.
- Disabled (with the emitted `(load ...)` `.php` preference) under `*build-mode*`, so `phel build` recompiles and harvests the stdlib. `PhelSourceLoader::load` keeps an outer build mode across every `(load ...)`.

### `--timing`

- Installs `PhaseTimingProfilerHook` via `Registry::setProfilerHook()` around `compileProject` (reset in `finally`), summing lex/parse/read/analyze/emit wall-clock into `PhaseTimingReport`.
- `wrapFn()` is a deliberate no-op: a build evaluates `def`/`defmacro`, so profiling proxies would be baked into output. Pair with `--no-cache` to compare.

### `--report`

- `BuildReport::fromCompiledFiles` takes `CompiledFile[]` + duration: namespace count, per-namespace byte size (read from each target), total, fresh/cached counts. Pure VO; `BuildCommand` renders it.

### Lifecycle

- `FileEvaluator` is a singleton so repeated `(load ...)` calls keep the compiled-code index.
