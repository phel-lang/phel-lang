---
name: module-build
description: 'Build module: Compiles Phel projects to PHP.'
scope: src/php/Build
---

# Build Module

Namespace extraction, dependency ordering, compilation and caching.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)` | Extract `NamespaceInformation` (VO in `Phel\Shared`; Build produces it). See Namespace extraction errors below |
| `getDependenciesForNamespace(array $dirs, array $ns)` | Topologically sorted dependencies. See Dependency resolution below |
| `unresolvedRequireError(required, requiring, requiringFile, searchedDirs)` | Error for a require that matched no source file, or null when `BundledNamespaceIndex` says it resolves anyway. The emitted `ns` form calls it on its miss path and throws the result |
| `compileFile(src, dest)` | Compile to PHP, write output |
| `evalFile(src)` | `compileFile` without writing output |
| `flushCompiledCodeCache()` | Flushes the compiled-code index and dependency graph; a parent calls it before spawning workers so they see both |
| `compileProject(Shared\BuildOptions)` | Returns `CompiledFile[]` |
| `clearCache()` | Returns `string[]` paths cleared from the temp, cache and OPcache dirs |
| `getHealthCheck()` | Cache, output, source dir checks |
| `enableBuildMode()` / `disableBuildMode()` / `isBuildMode()` | Static; toggles `*build-mode*` via direct `Registry` write (avoids `Phel::__callStatic` on the hot `(load ...)` path) |
| `enterDependencyLoad()` / `leaveDependencyLoad(bool)` / `isLoadingDependencies()` | Static; flag the region where an `ns` form evaluates its required files (`BuildConstants::LOADING_DEPENDENCIES`, not build mode) |
| `writeLocatedException` / `writeStackTrace` / `getOutputDirectory` | Delegate to Command facade |

### Namespace extraction errors

- First form not `ns`/`in-ns` and fails on an unresolved symbol: asking for that file throws `Shared\Exceptions\MissingNsFormException`; `phel run <file>` still gets the hint.
- An `ns` form the analyzer rejects (`(:require [x :refer :all])`) throws `Shared\Exceptions\CompilerException` with its snippet, located in the file. `ProjectCompiler` passes `failOnInvalidNsForm: true`, so `build` fails naming the file (#3457).
- A directory scan (`NamespaceExtractor` and `CachedNamespaceExtractor`) skips both cases, and unparseable files. Reason: the scan walks the cwd too, and one stray `.cljc` or bad file must not stop `phel eval`, `run`, `lint` or the REPL (#3484). The scan index still stamps a skipped file's mtime, so it is reread only once it changes (#3629).

### Dependency resolution

- Seeds, index keys and declared dependencies are canonicalised with `Munge::canonicalNs`, so either namespace separator resolves (`Watch` passes the backslash form).
- Throws `ExtractorException` when a resolved namespace's `(:require ...)` names nothing loadable: no source file, no `clojure.*` remap target (`FrameworkNamespaces::clojureTarget`, `clojure.set` is `phel.core`) in the scan, not in the registry, and not a `phel.*` namespace `BundledNamespaceIndex` finds on the configured source and vendor dirs (a vendored scan can lack the stdlib, so the caller's index is not enough). The message suggests the closest known namespace (`phel.strng` -> `phel.string`).
- `MissingRequireReporter` rereads the requiring `ns` form and throws `CompilerException` wrapping `MissingNamespaceException` (`PHEL014`), caret on the required namespace, searched dirs as note; without a readable requiring file (REPL, eval), a bare `ExtractorException`.
- A `clojure.*` seed walks its `phel.*` target. Other unresolved *seeds* are tolerated (callers like the REPL check the empty result).

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `CompilerFacadeInterface::class` | Phel-to-PHP compilation |
| `CommandFacadeInterface::class` | Output/source dirs, error formatting |

Both are injected as their Shared `*FacadeInterface`. One non-facade edge, **Config**: `BuildConfig` reads `PhelConfig`/`PhelBuildConfig`, and `Domain/Compile/Output/EntryPointPhpFile` reads `PhelBuildConfig`.

### Version stamping

- `EntryPointPhpFile` emits `\Phel::assertBuiltWith(...)` after Composer autoloading and before bootstrap, then `\Phel::setupRuntimeArgs(...)`.
- Built files and exported wrappers carry `VersionResolver::current()` (dev commit suffix included) and refuse another runtime version; rebuild after any Phel version change. Generated calls to internals are no cross-version ABI.
- `BuildConfig::getLockedPhelVersion()` / `getLockedPhelReference()` read the Composer lock so `BuildCommand` warns on a different Phel. Dev dependencies compare by source or dist commit, not branch; an unknown reference is no mismatch.

## Structure

| Path | Role |
|------|------|
| `Application/ProjectCompiler` | Orchestrates project build, cache invalidation cascade |
| `Application/FileEvaluator` | Eval single file, precompiled-sibling fast path |
| `Application/FileCompiler` | Compile single file to PHP |
| `Application/DependenciesForNamespace` | Per-process memoized dependency resolution |
| `Application/CachedNamespaceExtractor` | Skips dir walk via scan index |
| `Application/CacheClearer` | Clears `<tempDir>` + `<cacheDir>`; empties `<phelDir>/opcache` in place |
| `Domain/Extractor/TopologicalNamespaceSorter` | Dependency-order compilation |
| `Domain/Compile/BuildReport` + `BuildReportEntry` | `--report` VO |
| `Domain/Compile/PhaseTimingReport` | `--timing` per-phase report |
| `Domain/Compile/SecondaryFileHarvester` | Writes `(in-ns ...)` secondary `.php` siblings into the output tree, from the compiled-code cache, else `CompiledSecondaryStore` (so a cache-off build still emits them) |
| `Domain/Compile/SymbolMetaStripper` | Token-based removal of `\Phel::locationMeta(...)` args when `strip-symbol-meta` is on (write path only) |
| `Domain/Compile/CompiledSecondaryStore` | In-memory hand-off of build-time secondaries from `FileEvaluator` to `SecondaryFileHarvester` when the compiled-code cache is off |
| `Infrastructure/Cache/CompiledCodeCache` | Compiled-code cache policy orchestrator |
| `Infrastructure/Cache/EnvCacheFingerprint` | Hash of declared `cache-env-vars`, mixed into the compiled-code cache key |
| `Infrastructure/Cache/PhpScanIndexCache` | Persisted dir-scan index |
| `Domain/Cache/NullScanIndexCache` | No-op scan index (in `Domain` so `Application` never imports outward) |
| `Infrastructure/Cache/PhpNamespaceCache` / `NullNamespaceCache` | Namespace-extraction cache |
| `Infrastructure/Cache/LockedPhpCacheWriter` | Shared flock'd `var_export` write for `PhpNamespaceCache` + `PhpScanIndexCache` |
| `Infrastructure/Timing/PhaseTimingProfilerHook` | `--timing` profiler hook |
| `Infrastructure/Command/BuildCommand` / `CacheClearCommand` | CLI |

## Key Constraints

### Caching (two levels: namespace extraction + compiled code, each optional)

- **Compiled-code cache** (`CompiledCodeCache`) keys entries by `CompiledSourceHash::of(source, optimizationLevel, envFingerprint)` under an index stamped with the running Phel version (beta commits included). Delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`.
- An entry stores its compile's deprecation notices (`EmitterResult::getDeprecations()`, index field `deprecations`), replayed by `FileEvaluator` via `CompilerFacadeInterface::replayDeprecations()` on every hit, so `--warn-deprecations` matches warm and cold (#3222). Never key the cache on the flag: a repeat flagged run would go silent. `NamespaceExtractor` reads `ns` forms inside `withoutDeprecations()`: notices come from compiling, never indexing (#3381). `INDEX_FORMAT_VERSION` 1.10 rejects older namespace environments that lack default PHP-import provenance, preserving default-alias overrides on warm loads (#3718).
- `put`/`invalidate` mutate the in-memory index only; it flushes **once per process at shutdown** (`DeferredFlushTrait`), keeping cold-build index I/O O(N), not O(N²). `CacheIndexFile::save()` does atomic write + `flock` + disk merge, so concurrent `phel test` workers lose no entries. Compiled `.php` files are written eagerly (`AtomicFileWriter`), so a crash costs a recompile, never corruption. `clear()` writes the empty index eagerly.
- **Test gotcha:** tests needing cross-instance disk persistence in one process must call `save()` explicitly.
- **Scan-index cache** (`PhpScanIndexCache`, `<cacheDir>/scan-index.php`, impl `ScanIndexCacheInterface`; `NullScanIndexCache` when disabled) lets `CachedNamespaceExtractor` skip the directory walk across processes. Keyed by resolved dir-set; served only if all hold:
  - per-dir `mtime` + phel-file count match (same-second add/remove);
  - per-file `mtime` matches (in-place edits);
  - every per-file `mtime` is older than the entry's `recordedAt`;
  - `ScanIndexEntry::covers()` the requested directories that exist now (only this catches a configured dir that appears after the scan, #3205).
  
  Same mechanics as `PhpNamespaceCache`. Injected via `BuildFactory::createScanIndexCache()`; path from `BuildConfig::getScanIndexCacheFile()`; cleared by `CacheClearer`.
- **The namespace and scan-index caches prune unreachable entries in `save()`**, after the disk merge, before the write. Unreachable means the anchor path is *gone* (`ScanIndexEntry::isReachable()` probes `perDir` keys; `PhpNamespaceCache` the file key), not out of date (a changed mtime gets overwritten by the next scan). Otherwise the merge keeps dead entries forever, e.g. the unique `.phel_temp_<uniqid>` dir `Api\Infrastructure\PhelFunctionRuntimeLoader` scans per `phel doc` / LSP hover / REPL completion (#3007). Never prune at load: `isValid()` already rejects dead entries, so it would only add a `stat` per entry to every start.
- **Racily clean entries are never served.** `filemtime` is whole-second. `NamespaceCacheEntry` and `ScanIndexEntry` carry `recordedAt`, taken *before* the read or walk; a file with mtime not older than it is reread (git's racily clean rule, #3537). Tests expecting a warm hit must backdate their files. `ProjectCompiler`'s incremental reuse (target mtime equals source mtime) does the same: a source not older than its compile start gets target mtime 0, forcing a recompile (#3564).
- `DependenciesForNamespace` memoizes per `(dirs, seeds)` within a process for its three root callers (`FileRunner`, `DataReadersLoader`, `NamespaceLoader`).

### OPcache file cache

- `bin/phel` re-execs with `opcache.file_cache=<phel-dir>/opcache` (`PhelProjectDirectory::opcachePath()`, ignores configured `phel-dir`: the CLI resolves it before config loads). It must be pruned or it grows without bound (#3268).
- `CacheClearer` empties it **in place** via `OpcacheFileCachePruner::clearContents()`, not `deleteDirectory()`: PHP aborts at startup when `opcache.file_cache` points at a missing path.
- `bin/phel` prunes on start, in the re-exec'd child only: foreign system-id subtrees and orphaned `.bin` files below the system temp root (eval temp files). The system id is probed (`OpcacheFileCache::detectSystemId()` reads the running script's `.bin`); userland cannot compute it.
- `opcache_invalidate()` cannot drop a deleted file's `.bin` under `opcache.file_cache_only=1` (the CLI's mode); delete the `.bin`.

### Compilation order & invalidation

- `DependencyTracker` stores each file's source and owning namespace. Graph writes defer to shutdown (`DeferredFlushTrait`) or explicit `save()`; `LockedPhpCacheWriter` holds a lock while atomically merging disk and in-memory edges, preserving concurrent workers' dependencies. Namespace invalidation completes the transitive cascade before returning, with visited sets for cycles, so later processes cannot serve stale dependents through another entry point. Do not cascade on every cold miss: core `(load ...)` fragments share `phel.core`, so that evicts valid siblings.
- `ProjectCompiler` relies on `TopologicalNamespaceSorter` order: it force-recompiles any dependent whose `getDependencies()` includes a namespace recompiled this run (`dependsOnRecompiled`), even with unchanged mtime. Transitive in one pass, so a changed macro never leaves a stale expansion.
- Main namespace auto-detect: `core.phel` or `main.phel` in source dirs.
- The output dir is excluded from extraction (prevents namespace shadowing).
- Phel's own `src/php` is pruned from every scan (`ExcludedScanPaths`, even `none()`): the internal `src/` is every run's first source dir, and recounting its ~1,200 PHP files slows warm runs (#3467).

### Build markers in `<out>`

`phel build`'s incremental check is mtime-only, so `ProjectCompiler` writes a marker per setting and force-recompiles when it changes. The default value leaves no marker.

| Marker | Setting |
|--------|---------|
| `.phel-optimization-level` (`OPTIMIZATION_LEVEL_FILE`) | optimization level (0 = none) |
| `.phel-cache-env-fingerprint` | `cache-env-vars` fingerprint (empty = none) |
| `.phel-strip-symbol-meta` | `strip-symbol-meta` |

### Optimization level

- `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) is injected into `FileCompiler` (constructor default; per-call override wins, used by `phel build -O`) and `FileEvaluator` (mixed into the compiled-code cache hash when > 0).

### cache-env-vars

- `PhelConfig::withCacheEnvVars(['MY_MODE'])` (key `cache-env-vars`, default none) names env vars that join the compiled-code cache key. `EnvCacheFingerprint::of()` hashes sorted, deduplicated name + `md5(value)` pairs (unset is `-`, not empty). `BuildFactory::cacheEnvFingerprint()` injects it into `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader), both mixing it into `CompiledSourceHash::of()`. Values are hashed, never stored, so a secret is safe to declare.
- Why: `(php/getenv "MY_MODE")` in a macro bakes the value into emitted PHP while the key sees only source (#3236).
- One project-wide fingerprint: flipping a value invalidates every entry, overwritten in place (`CachePathResolver::compiledPath()` keys on namespace + source *path*). Alternating values want a `cache-dir` each.
- Declaring nothing leaves the hash byte-identical. No `INDEX_FORMAT_VERSION` bump needed: a differing fingerprint already misses.
- The fingerprint marker is required: without it the reuse path `require_once`s the old primary, whose build-mode `(load ...)` misses its secondary, which recompiles against a half-registered registry: `Cannot resolve symbol 'map'`.
- Not covered: the precompiled-sibling fast path (keeps its expansion until rebuilt) and `Lint`'s diagnostic cache (source + rules).

### strip-symbol-meta

- `PhelConfig::withStripSymbolMeta()` (key `strip-symbol-meta`, default off) makes `FileCompiler` and `SecondaryFileHarvester` strip def metadata from written artifacts. Write path only: build-time evaluation keeps full meta so downstream compilation keeps inference/arity data.
- Stripped builds also drop source maps (line numbers shift). The marker is required because a stripped target must never be reused as compile cache: its `require_once` would register meta-less defs.

### Source maps

- `FileEvaluator` caches `getCodeWithSourceMap()`, so errors from cached namespaces map to `.phel` via the `// `/`// ;;` header comments. `CompiledCodeCache::put` over a file this process `require`d keeps its old header in `Shared\SourceMap\SupersededSourceMaps` (memory only), and `get` stops serving that path for the rest of the process, so one path never runs two versions.

### Precompiled-sibling fast path

- Before the cache, `FileEvaluator::evalFile` `require_once`s a `BuiltFilePreamble`-marked `<name>.php` next to the `.phel`/`.cljc` source and returns. `require_once`, not `require`: a second load must not re-run the primary and re-null its forward-declared defs (#2673).
- The sibling is reused only when its version stamp matches the running Phel version; an older or unstamped one falls back to compiling the source. `BuiltFilePreamble::isPresent()` stays tolerant for source-map recognition.
- The PHAR ships `phel.core` this way (`build/build-phar.php::addPrecompiledStdlibSiblings`); running it populates the registry (defs + macro meta), enough for the analyzer.
- Bundle a namespace only with its full transitive `(:require ...)` closure, since FILE-mode output `require_once`s dependency siblings directly; `phel.core` is self-contained.
- Disabled (with the emitted `(load ...)` `.php` preference) under `*build-mode*`, so `phel build` recompiles and harvests the stdlib. `PhelSourceLoader::load` keeps an outer build mode across every `(load ...)`.

### `--timing` profiler hook

- Installs `PhaseTimingProfilerHook` via `Registry::setProfilerHook()` around `compileProject` (reset in `finally`), summing lex/parse/read/analyze/emit wall-clock into `PhaseTimingReport`.
- `wrapFn()` is a deliberate no-op: a build evaluates `def`/`defmacro`, so profiling proxies would be baked into output. Pair with `--no-cache` to compare.

### `--report`

- `BuildReport::fromCompiledFiles` takes `CompiledFile[]` + duration: namespace count, per-namespace byte size (read from each target), total, fresh/cached counts. Pure VO; `BuildCommand` renders it.

### Lifecycle

- `FileEvaluator` is a singleton so repeated `(load ...)` calls keep the compiled-code index.
