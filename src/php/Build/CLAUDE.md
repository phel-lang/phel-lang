# Build Module

Compiles Phel projects to PHP: dependency resolution, caching, namespace extraction.

## Public API (Facade)

- `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)`: extract `NamespaceInformation` (the VO lives in `Phel\Shared`, not here; Build produces it)
- `getDependenciesForNamespace(array $dirs, array $ns)`: topologically sorted dependencies
- `compileFile(src, dest)` / `evalFile(src)` / `compileProject(BuildOptions)`: compile to PHP (evalFile skips writing output)
- `phel build --report` builds a `BuildReport` (`Domain/Compile/BuildReport` + `BuildReportEntry`) from the returned `CompiledFile[]` + build duration: namespace count, per-namespace compiled byte size (read from each target file), total size, fresh/cached counts. Pure VO with `toArray()`; the command renders it
- `clearCache(): string[]`: paths cleared from temp/cache dirs
- `getHealthCheck()`: cache, output, source dir checks
- `writeLocatedException` / `writeStackTrace` / `getOutputDirectory`: delegate to Command facade

## Dependencies

Compiler (Phel-to-PHP compilation), Command (output/source dirs, error formatting).

## Key Constraints

- Two-level caching: namespace extraction (optional) + compiled code (optional)
- `FileEvaluator` compiles with source maps enabled and caches `getCodeWithSourceMap()`, so runtime errors from cache-loaded namespaces still map back to `.phel` locations via the inline `// `/`// ;;` header comments
- `Infrastructure/Cache/CompiledCodeCache` is the policy orchestrator; delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`
- `TopologicalNamespaceSorter` orders compilation to resolve dependencies
- `FileEvaluator` is singleton; repeated `(load ...)` calls reuse instance to preserve compiled-code index
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`
- Output directory pruned from extraction to prevent namespace shadowing
- Optimization level: `BuildConfig::getOptimizationLevel()` (key `PhelConfig::OPTIMIZATION_LEVEL`) is injected into `FileCompiler` (constructor default; per-call override wins, used by `phel build -O`) and `FileEvaluator` (also mixed into the compiled-code cache hash when > 0). `ProjectCompiler` records the level in `<out>/.phel-optimization-level` and forces a recompile when it changes, because the incremental cache is mtime-only; level 0 leaves no marker
- Precompiled-sibling fast path: before the compiled-code cache, `FileEvaluator::evalFile` checks for a `phel build`-style `<name>.php` next to the `<name>.phel`/`.cljc` source (detected via the `BuiltFilePreamble` marker). If present it `require`s it directly and returns, skipping the whole pipeline and the cache. This is how the PHAR ships `phel.core` precompiled (siblings added by `build/build-phar.php::addPrecompiledStdlibSiblings`): running the compiled file populates the runtime registry (defs + macro meta), which is all the analyzer needs to resolve those symbols when later compiling user code. Inert when no sibling exists (plain source / composer checkouts). A namespace may only be bundled together with its full transitive `(:require ...)` closure, since FILE-mode output `require_once`s its dependency siblings directly — `phel.core` qualifies because it is self-contained
