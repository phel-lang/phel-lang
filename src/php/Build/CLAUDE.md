# Build Module

Compiles Phel projects to PHP: dependency resolution, caching, namespace extraction.

## Public API (Facade)

- `getNamespaceFromFile(string)` / `getNamespaceFromDirectories(array)`: extract `NamespaceInformation` (the VO lives in `Phel\Shared`, not here; Build produces it)
- `getDependenciesForNamespace(array $dirs, array $ns)`: topologically sorted dependencies
- `compileFile(src, dest)` / `evalFile(src)` / `compileProject(BuildOptions)`: compile to PHP (evalFile skips writing output)
- `clearCache(): string[]`: paths cleared from temp/cache dirs
- `getHealthCheck()`: cache, output, source dir checks
- `writeLocatedException` / `writeStackTrace` / `getOutputDirectory`: delegate to Command facade

## Dependencies

Compiler (Phel-to-PHP compilation), Command (output/source dirs, error formatting).

## Key Constraints

- Two-level caching: namespace extraction (optional) + compiled code (optional)
- `Infrastructure/Cache/CompiledCodeCache` is the policy orchestrator; delegates to `CacheDirectory` (layout), `CacheIndexFile` (index load/save/merge), `NamespaceEnvironmentStore` (env data), `CachePathResolver`, `AtomicFileWriter`
- `TopologicalNamespaceSorter` orders compilation to resolve dependencies
- `FileEvaluator` is singleton; repeated `(load ...)` calls reuse instance to preserve compiled-code index
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`
- Output directory pruned from extraction to prevent namespace shadowing
