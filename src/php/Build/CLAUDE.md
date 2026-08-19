# Build Module

Compiles Phel projects to PHP: namespace extraction, dependency ordering, and caching.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)` | Extract `NamespaceInformation` (VO lives in `Phel\Shared`; Build produces it) |
| `getDependenciesForNamespace(array $dirs, array $ns)` | Topologically sorted dependencies. Seeds, index keys and declared dependencies are canonicalised with `Munge::canonicalNs`, so either namespace separator resolves (`Watch` hands over the backslash form). Throws `ExtractorException` when a resolved namespace's `(:require ...)` names a missing **user** namespace (no source file, no `clojure.*`→bundled-`phel.*` remap, not already in the registry) — a typo'd require is a fast error, not a silent drop. Framework-provided `phel.*`/`clojure.*` requires stay tolerated (precompiled+lazy-loaded stdlib / clojure-compat shims aren't in the source scan downstream). Unresolved *seeds* also stay tolerated (callers like the REPL check the empty result themselves) |
| `compileFile(src, dest)` | Compile to PHP, write output |
| `evalFile(src)` | Same as `compileFile` but skips writing output |
| `flushCompiledCodeCache()` | Writes the compiled-code cache index to disk now (it otherwise flushes once at shutdown); a parent about to spawn workers calls it so they find what it compiled |
| `compileProject(BuildOptions)` | Returns `CompiledFile[]` |
| `clearCache()` | Returns `string[]` paths cleared from temp/cache dirs |
| `getHealthCheck()` | Cache, output, source dir checks |
| `enableBuildMode()` / `disableBuildMode()` / `isBuildMode()` | Static; toggles `*build-mode*` via direct `Registry` write (avoids `Phel::__callStatic` on the hot `(load ...)` path) |
| `writeLocatedException` / `writeStackTrace` / `getOutputDirectory` | Delegate to Command facade |

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `CompilerFacadeInterface::class` | Phel-to-PHP compilation |
| `CommandFacadeInterface::class` | Output/source dirs, error formatting |

Both are injected as their Shared `*FacadeInterface`. One non-facade edge: **Config** — `BuildConfig` reads `PhelConfig`/`PhelBuildConfig`, and `Domain/Compile/Output/EntryPointPhpFile` reads `PhelBuildConfig`.

`EntryPointPhpFile` also *emits* the string `\Phel\Phel::setupRuntimeArgs(...)` into generated entry points. That is generated-code text, not an import: it creates no compile-time edge to the composition root, but it does make the root's signature part of the build ABI, so changing `setupRuntimeArgs()` breaks previously built artifacts.

## Structure

| Path | Role |
|------|------|
| `Application/ProjectCompiler` | Orchestrates project build, cache invalidation cascade |
| `Application/FileEvaluator` | Singleton; eval single file, precompiled-sibling fast path |
| `Application/FileCompiler` | Compile single file to PHP |
| `Application/DependenciesForNamespace` | Per-process memoized dependency resolution |
| `Application/CachedNamespaceExtractor` | Skips dir walk via scan index |
| `Application/CacheClearer` | Clears `<cacheDir>` |
| `Domain/Extractor/TopologicalNamespaceSorter` | Dependency-order compilation |
| `Domain/Compile/BuildReport` + `BuildReportEntry` | `--report` VO (`toArray()`); command renders it |
| `Domain/Compile/PhaseTimingReport` | `--timing` per-phase wall-clock report |
| `Domain/Compile/SecondaryFileHarvester` | Writes `(in-ns ...)` secondary `.php` siblings into the build output tree; takes them from the compiled-code cache, else from `CompiledSecondaryStore` (so a cache-off build still emits them) |
| `Domain/Compile/SymbolMetaStripper` | Token-based removal of `\Phel::locationMeta(...)` args from build output when `strip-symbol-meta` is on (write-path only; evaluation keeps full meta) |
| `Domain/Compile/CompiledSecondaryStore` | In-memory hand-off of build-time-compiled secondaries from `FileEvaluator` to `SecondaryFileHarvester` when the compiled-code cache is off |
| `Infrastructure/Cache/CompiledCodeCache` | Compiled-code cache policy orchestrator |
| `Infrastructure/Cache/EnvCacheFingerprint` | Hash of the declared `cache-env-vars`, mixed into the compiled-code cache key |
| `Infrastructure/Cache/PhpScanIndexCache` | Persisted dir-scan index |
| `Domain/Cache/NullScanIndexCache` | No-op scan index (Null Object; in `Domain` so `Application` never imports outward) |
| `Infrastructure/Cache/PhpNamespaceCache` / `NullNamespaceCache` | Namespace-extraction cache |
| `Infrastructure/Cache/LockedPhpCacheWriter` | Shared flock'd `var_export` cache-file write (used by `PhpNamespaceCache` + `PhpScanIndexCache`) |
| `Infrastructure/Timing/PhaseTimingProfilerHook` | `--timing` profiler hook |
| `Infrastructure/Command/BuildCommand` / `CacheClearCommand` | CLI |

## Key Constraints

### Caching (two levels: namespace extraction + compiled code, each optional)

- **Compiled-code cache** (`CompiledCodeCache`) keys entries by `CompiledSourceHash::of(source, optimizationLevel, envFingerprint)` under an index stamped with the Phel version; it is the policy orchestrator and delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`.
- A cache entry carries the deprecation notices its compile found (`EmitterResult::getDeprecations()`, stored as `deprecations` in the index entry, `INDEX_FORMAT_VERSION` 1.6); `FileEvaluator` hands them to `CompilerFacadeInterface::replayDeprecations()` on every hit, so `--warn-deprecations` reports the same on a warm cache as on a cold one (#3222). Never key the cache on the flag instead: a repeat flagged run would then be silent again.
- `put`/`invalidate` only mutate the in-memory index + mark it dirty; the index flushes to disk **exactly once per process at shutdown** via `register_shutdown_function` (`DeferredFlushTrait`), so cold-build index I/O is O(N) not O(N²). Flush goes through `CacheIndexFile::save()` (atomic-write + `flock` + read-merge-from-disk), so concurrent `phel test` workers merge without lost entries.
- Compiled `.php` files are still written eagerly by `AtomicFileWriter`, so a crash before shutdown costs at most a recompile (lost index entry), never corruption. `clear()` writes the empty index eagerly + resets the dirty flag.
- **Test gotcha:** tests needing cross-instance disk persistence in the same process must call `save()` explicitly (what a real process does at shutdown).
- **Scan-index cache** (`PhpScanIndexCache`, `<cacheDir>/scan-index.php`, impl `ScanIndexCacheInterface`; `NullScanIndexCache` when disabled) lets `CachedNamespaceExtractor` skip the `RecursiveDirectoryIterator` walk across processes. Keyed by resolved dir-set; validated by per-directory `mtime` + phel-file count (catches same-second add/remove) AND per-file `mtime` in each `ScanIndexEntry` (catches in-place edits) AND `ScanIndexEntry::covers()` against the requested directories that exist now (a configured directory absent at scan time has no fingerprint, so its later appearance can only be caught this way, #3205) — never serves stale ns/dependency info. Mirrors `PhpNamespaceCache` (var_export + flock + disk-merge + shutdown flush). Injected via `BuildFactory::createScanIndexCache()`; path from `BuildConfig::getScanIndexCacheFile()`; cleared by `CacheClearer`.
- **Both `LockedPhpCacheWriter` caches prune unreachable entries in `save()`**, after the disk merge and before the write. Unreachable means the anchor path is *gone* (`ScanIndexEntry::isReachable()` probes the `perDir` keys; `PhpNamespaceCache` probes the file key), not merely out of date: a changed `mtime` still resolves to a reachable key that the next scan overwrites, whereas a removed directory can never produce its key again. Without this the merge inherits every dead entry from every previous run, and the cache grows forever. The path that forced it is `Api\Infrastructure\PhelFunctionRuntimeLoader`, which scans a unique `.phel_temp_<uniqid>` directory per `phel doc` / LSP hover / REPL completion and removes it in a `finally` that runs *before* the shutdown flush, so each call was appending ~156 KB of permanently dead index (#3007). Prune at save, never at load: reads already reject dead entries via `isValid()`, so load-time pruning would buy no correctness while charging a `stat` per entry to every process start, read-only runs included.
- `DependenciesForNamespace` memoizes per `(dirs, seeds)` within a process so the three root callers (`FileRunner`, `DataReadersLoader`, `NamespaceLoader`) don't each re-derive.

### Compilation order & invalidation

- `TopologicalNamespaceSorter` orders compilation to resolve dependencies. `ProjectCompiler` relies on this order: it tracks namespaces recompiled during a run and force-recompiles any dependent whose `getDependencies()` includes one of them (`dependsOnRecompiled`), even when the dependent's own source mtime is unchanged. Cascades transitively in one pass — prevents a changed macro leaving a stale expansion baked into a dependent's compiled file.
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`.
- Output directory is pruned from extraction to prevent namespace shadowing.

### Optimization level

- `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) injected into `FileCompiler` (constructor default; per-call override wins, used by `phel build -O`) and `FileEvaluator` (also mixed into the compiled-code cache hash when > 0).
- `ProjectCompiler` records the level in `<out>/.phel-optimization-level` (`OPTIMIZATION_LEVEL_FILE`) and force-recompiles when it changes, because the incremental cache is mtime-only; level 0 leaves no marker.

### cache-env-vars

- `PhelConfig::withCacheEnvVars(['MY_MODE'])` (key `cache-env-vars`, default none) names the environment variables that take part in the compiled-code cache key. `EnvCacheFingerprint::of()` hashes name + `md5(value)` pairs over the sorted, deduplicated names (unset reads as `-`, distinct from empty) and `BuildFactory::cacheEnvFingerprint()` injects the result into `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader), both of which mix it into `CompiledSourceHash::of()`. Values are hashed, never stored, so a secret is safe to declare.
- Why it is needed: macro expansion happens at compile time, so `(php/getenv "MY_MODE")` inside a macro bakes the value into the emitted PHP while the key sees only source. A changed variable used to serve the previous expansion (#3236).
- One fingerprint covers the whole project, so flipping a declared value invalidates every entry; the compiled file is overwritten in place (`CachePathResolver::compiledPath()` keys the filename on namespace + source *path*), so nothing is orphaned. A config that alternates between values wants a `cache-dir` per value instead.
- Declaring nothing leaves the hash byte-identical to before, so existing caches stay warm. No `INDEX_FORMAT_VERSION` bump: the entry shape is unchanged and a differing fingerprint already misses.
- `ProjectCompiler` records the fingerprint in `<out>/.phel-cache-env-fingerprint` and force-recompiles when it changes, next to the optimization-level and strip markers. Not optional: `phel build`'s incremental check is mtime-only, so the reuse path `require_once`s the previous primary, whose build-mode `(load ...)` then asks the compiled-code cache for a secondary that the new fingerprint no longer keys — the secondary recompiles standalone against a half-registered registry and the build dies with `Cannot resolve symbol 'map'`. An empty fingerprint leaves no marker.
- Not covered: the precompiled-sibling fast path. A `BuiltFilePreamble`-marked `.php` next to a source skips the cache and the fingerprint both, so a bundle built under one environment keeps its expansion until the sibling itself is rebuilt. Same for `Lint`'s diagnostic cache, whose key is source + rules.

### strip-symbol-meta

- `PhelConfig::withStripSymbolMeta()` (key `strip-symbol-meta`, default off) makes `FileCompiler` and `SecondaryFileHarvester` strip def metadata from written artifacts (−28% size, −40% cold require on this repo's own build). Strip happens on the WRITE path only — build-time evaluation feeds the registry full meta, so downstream namespace compilation keeps inference/arity data.
- Stripped builds also drop source maps (line numbers shift) and leave `<out>/.phel-strip-symbol-meta`; flipping the flag force-recompiles, mirroring the optimization-level marker, because a stripped target must never be reused as compile cache (its `require_once` would register meta-less defs).

### Source maps

- `FileEvaluator` compiles with source maps enabled and caches `getCodeWithSourceMap()`, so runtime errors from cache-loaded namespaces still map back to `.phel` locations via the inline `// `/`// ;;` header comments.

### Precompiled-sibling fast path

- Before the compiled-code cache, `FileEvaluator::evalFile` checks for a `phel build`-style `<name>.php` next to the `<name>.phel`/`.cljc` source (detected via the `BuiltFilePreamble` marker). If present it `require_once`s it directly and returns — skipping the whole pipeline and the cache. `require_once` (not `require`) keeps a re-`evalFile` idempotent: a second load must not re-run the primary and re-null its forward-declared defs (#2673).
- This is how the PHAR ships `phel.core` precompiled (siblings added by `build/build-phar.php::addPrecompiledStdlibSiblings`): running the compiled file populates the runtime registry (defs + macro meta), which is all the analyzer needs to resolve those symbols when later compiling user code. Inert when no sibling exists (plain source / composer checkouts).
- A namespace may only be bundled together with its full transitive `(:require ...)` closure, since FILE-mode output `require_once`s its dependency siblings directly — `phel.core` qualifies because it is self-contained.
- The fast path (and the emitted `(load ...)` `.php` preference) is disabled while `*build-mode*` is on, so `phel build` recompiles + harvests the stdlib into its output tree instead of short-circuiting to the bundle. `PhelSourceLoader::load` preserves an outer build mode so it stays on across every `(load ...)` of a build.

### `--timing` profiler hook

- `phel build --timing` installs `PhaseTimingProfilerHook` as `Registry::$profilerHook` around `compileProject` (reset in `finally`) to sum the compiler's per-phase wall-clock (lex/parse/read/analyze/emit) across compiled namespaces, rendered as `PhaseTimingReport`.
- The hook's `wrapFn()` is a deliberate no-op — a build evaluates `def`/`defmacro` while compiling, so wrapping those fns in profiling proxies (what the runtime profiler does) would bake instrumentation into the emitted output. Pair with `--no-cache` for a full, comparable measurement.

### `--report`

- `phel build --report` builds a `BuildReport` (`BuildReport::fromCompiledFiles`) from the returned `CompiledFile[]` + build duration: namespace count, per-namespace compiled byte size (read from each target file), total size, fresh/cached counts. Pure VO with `toArray()`; `BuildCommand` renders it.

### Lifecycle

- `FileEvaluator` is a singleton; repeated `(load ...)` calls reuse the instance to preserve the compiled-code index.
