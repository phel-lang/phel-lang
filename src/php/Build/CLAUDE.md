# Build Module

Compiles Phel projects to PHP: namespace extraction, dependency ordering, and caching.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)` | Extract `NamespaceInformation` (VO lives in `Phel\Shared`; Build produces it) |
| `getDependenciesForNamespace(array $dirs, array $ns)` | Topologically sorted dependencies. Throws `ExtractorException` when a resolved namespace's `(:require ...)` names a missing **user** namespace (no source file, no `clojure.*`→bundled-`phel.*` remap, not already in the registry) — a typo'd require is a fast error, not a silent drop. Framework-provided `phel.*`/`clojure.*` requires stay tolerated (precompiled+lazy-loaded stdlib / clojure-compat shims aren't in the source scan downstream). Unresolved *seeds* also stay tolerated (callers like the REPL check the empty result themselves) |
| `compileFile(src, dest)` | Compile to PHP, write output |
| `evalFile(src)` | Same as `compileFile` but skips writing output |
| `compileProject(BuildOptions)` | Returns `CompiledFile[]` |
| `clearCache()` | Returns `string[]` paths cleared from temp/cache dirs |
| `getHealthCheck()` | Cache, output, source dir checks |
| `enableBuildMode()` / `disableBuildMode()` / `isBuildMode()` | Static; toggles `*build-mode*` via direct `Registry` write (avoids `Phel::__callStatic` on the hot `(load ...)` path) |
| `writeLocatedException` / `writeStackTrace` / `getOutputDirectory` | Delegate to Command facade |

## Dependencies

| Facade | Used for |
|--------|----------|
| `FACADE_COMPILER` | Phel-to-PHP compilation |
| `FACADE_COMMAND` | Output/source dirs, error formatting |

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
| `Infrastructure/Cache/PhpScanIndexCache` / `NullScanIndexCache` | Persisted dir-scan index |
| `Infrastructure/Cache/PhpNamespaceCache` / `NullNamespaceCache` | Namespace-extraction cache |
| `Infrastructure/Timing/PhaseTimingProfilerHook` | `--timing` profiler hook |
| `Infrastructure/Command/BuildCommand` / `CacheClearCommand` | CLI |

## Key Constraints

### Caching (two levels: namespace extraction + compiled code, each optional)

- **Compiled-code cache** (`CompiledCodeCache`) is the policy orchestrator; delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`.
- `put`/`invalidate` only mutate the in-memory index + mark it dirty; the index flushes to disk **exactly once per process at shutdown** via `register_shutdown_function` (`DeferredFlushTrait`), so cold-build index I/O is O(N) not O(N²). Flush goes through `CacheIndexFile::save()` (atomic-write + `flock` + read-merge-from-disk), so concurrent `phel test` workers merge without lost entries.
- Compiled `.php` files are still written eagerly by `AtomicFileWriter`, so a crash before shutdown costs at most a recompile (lost index entry), never corruption. `clear()` writes the empty index eagerly + resets the dirty flag.
- **Test gotcha:** tests needing cross-instance disk persistence in the same process must call `save()` explicitly (what a real process does at shutdown).
- **Scan-index cache** (`PhpScanIndexCache`, `<cacheDir>/scan-index.php`, impl `ScanIndexCacheInterface`; `NullScanIndexCache` when disabled) lets `CachedNamespaceExtractor` skip the `RecursiveDirectoryIterator` walk across processes. Keyed by resolved dir-set; validated by per-directory `mtime` + phel-file count (catches same-second add/remove) AND per-file `mtime` in each `ScanIndexEntry` (catches in-place edits) — never serves stale ns/dependency info. Mirrors `PhpNamespaceCache` (var_export + flock + disk-merge + shutdown flush). Injected via `BuildFactory::createScanIndexCache()`; path from `BuildConfig::getScanIndexCacheFile()`; cleared by `CacheClearer`.
- `DependenciesForNamespace` memoizes per `(dirs, seeds)` within a process so the three root callers (`FileRunner`, `DataReadersLoader`, `NamespaceLoader`) don't each re-derive.

### Compilation order & invalidation

- `TopologicalNamespaceSorter` orders compilation to resolve dependencies. `ProjectCompiler` relies on this order: it tracks namespaces recompiled during a run and force-recompiles any dependent whose `getDependencies()` includes one of them (`dependsOnRecompiled`), even when the dependent's own source mtime is unchanged. Cascades transitively in one pass — prevents a changed macro leaving a stale expansion baked into a dependent's compiled file.
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`.
- Output directory is pruned from extraction to prevent namespace shadowing.

### Optimization level

- `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) injected into `FileCompiler` (constructor default; per-call override wins, used by `phel build -O`) and `FileEvaluator` (also mixed into the compiled-code cache hash when > 0).
- `ProjectCompiler` records the level in `<out>/.phel-optimization-level` (`OPTIMIZATION_LEVEL_FILE`) and force-recompiles when it changes, because the incremental cache is mtime-only; level 0 leaves no marker.

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
