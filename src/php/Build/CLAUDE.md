# Build Module

Core orchestrator for compiling Phel projects to PHP: dependency resolution, caching, namespace extraction.

## Gacela Pattern

- **Facade**: `BuildFacade` (implements `BuildFacadeInterface`)
- **Factory**: `BuildFactory` (extends `AbstractFactory<BuildConfig>`)
- **Config**: `BuildConfig` (cache/temp dirs, namespace cache flags, paths to ignore, entry point detection)
- **Provider**: `BuildProvider` (injects `CompilerFacade`, `CommandFacade`)

## Public API (Facade)

| Method | Return | Purpose |
|--------|--------|---------|
| `getNamespaceFromFile(string $filename)` | `NamespaceInformation` | Extract namespace from single file |
| `getNamespaceFromDirectories(array $dirs)` | `NamespaceInformation[]` | Extract all namespaces from dirs |
| `getDependenciesForNamespace(array $dirs, array $ns)` | `NamespaceInformation[]` | Topologically sorted dependencies for namespaces |
| `compileFile(string $src, string $dest)` | `CompiledFile` | Compile single file to PHP, write to dest |
| `evalFile(string $src)` | `CompiledFile` | Compile without writing output |
| `compileProject(BuildOptions $opts)` | `CompiledFile[]` | Compile entire project |
| `writeLocatedException(OutputInterface, CompilerException)` | `void` | Delegate exception formatting to Command |
| `writeStackTrace(OutputInterface, Throwable)` | `void` | Delegate stack trace formatting to Command |
| `clearCache()` | `string[]` | Paths cleared from temp/cache dirs |
| `getOutputDirectory()` | `string` | Delegate to Command facade |
| `getHealthCheck()` | `ModuleHealthCheckInterface` | Cache, output, source dir health checks |

## Dependencies

| Name | Injected as | Purpose |
|------|-------------|---------|
| Compiler | `CompilerFacade` (via Provider) | Phel-to-PHP compilation |
| Command | `CommandFacade` (via Provider) | Output/source dirs, error formatting |

## Structure

```
Build/
├── Application/        ProjectCompiler, FileCompiler, FileEvaluator, NamespaceExtractor (cached)
├── Domain/             Cache/, Compile/, Extractor/, IO/ (interfaces, value objects, sorters)
├── Infrastructure/     Cache/, IO/ (concrete implementations)
└── Gacela files        Facade, Factory, Config, Provider
```

## Key Constraints

- Two-level caching: namespace extraction (optional) + compiled code (optional)
- `TopologicalNamespaceSorter` orders compilation to resolve dependencies
- `FileEvaluator` is singleton; repeated `(load ...)` calls reuse instance to preserve compiled-code index
- Auto-detect main namespace: scans source dirs for `core.phel` or `main.phel`
- Excluded paths in extraction: output directory pruned to prevent namespace shadowing
