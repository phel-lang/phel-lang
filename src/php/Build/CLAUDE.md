# Build Module

Compiles Phel projects to PHP: dependency resolution, caching, namespace extraction.

## Public API (Facade)

- `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)`: extract `NamespaceInformation` (the VO lives in `Phel\Shared`, not here; Build produces it)
- `getDependenciesForNamespace(array $dirs, array $ns)`: topologically sorted dependencies
- `compileFile(src, dest)` / `evalFile(src)` / `compileProject(BuildOptions)`: compile to PHP (evalFile skips writing output)
- `phel build --report` builds a `BuildReport` (`Domain/Compile/BuildReport` + `BuildReportEntry`) from the returned `CompiledFile[]` + build duration: namespace count, per-namespace compiled byte size (read from each target file), total size, fresh/cached counts. Pure VO with `toArray()`; the command renders it
- `clearCache(): string[]`: paths cleared from temp/cache dirs
- `precompileBundledStdlib(string $targetDir): int`: exports the populated compiled-code cache into a read-only, content-addressed bundle (caller compiles the bundled namespaces first). Used at PHAR build time
- `getHealthCheck()`: cache, output, source dir checks
- `writeLocatedException` / `writeStackTrace` / `getOutputDirectory`: delegate to Command facade

## Dependencies

Compiler (Phel-to-PHP compilation), Command (output/source dirs, error formatting).

## Key Constraints

- Two-level caching: namespace extraction (optional) + compiled code (optional)
- Bundled precompiled stdlib: `Infrastructure/Cache/BundledCompiledCache` is a read-only, content-addressed (keyed by source content hash, not path → install-location independent) fallback consulted by `CompiledCodeCache` on `get()`/`getEnvironment()` misses. `Application/BundledPrecompiler::exportFromCache()` re-keys a populated cache's `phel.*` entries into the bundle. `BuildConfig::getBundledPrecompiledDir()` resolves `<pkgRoot>/cache/precompiled` (present only in the PHAR; null/inert otherwise; `PHEL_BUNDLED_PRECOMPILED_DIR` overrides)
- `FileEvaluator` compiles with source maps enabled and caches `getCodeWithSourceMap()`, so runtime errors from cache-loaded namespaces still map back to `.phel` locations via the inline `// `/`// ;;` header comments
- `Infrastructure/Cache/CompiledCodeCache` is the policy orchestrator; delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`
- `TopologicalNamespaceSorter` orders compilation to resolve dependencies
- `FileEvaluator` is singleton; repeated `(load ...)` calls reuse instance to preserve compiled-code index
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`
- Output directory pruned from extraction to prevent namespace shadowing
- Optimization level: `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) is injected into `FileCompiler` (constructor default; per-call override wins, used by `phel build -O`) and `FileEvaluator` (also mixed into the compiled-code cache hash when > 0). `ProjectCompiler` records the level in `<out>/.phel-optimization-level` and forces a recompile when it changes, because the incremental cache is mtime-only; level 0 leaves no marker
