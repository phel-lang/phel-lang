# Build Module

Core build orchestrator: compiles Phel projects to PHP with dependency resolution and caching.

## Gacela Pattern

- **Facade**: `BuildFacade` implements `BuildFacadeInterface`
- **Factory**: `BuildFactory` extends `AbstractFactory<BuildConfig>`
- **Config**: `BuildConfig` : cache settings, paths to ignore, temp/cache dirs, entry point detection
- **Provider**: `BuildProvider` : injects `CompilerFacade` (`FACADE_COMPILER`) and `CommandFacade` (`FACADE_COMMAND`)

## Public API (Facade)

- `getNamespaceFromFile(string): NamespaceInformation` : extract namespace from file
- `getNamespaceFromDirectories(array): array` : extract all namespaces from directories
- `getDependenciesForNamespace(array, array): array` : topologically sorted dependencies
- `compileFile(string, string): CompiledFile` : compile single file to PHP
- `evalFile(string): CompiledFile` : evaluate without writing output
- `compileProject(BuildOptions): array` : compile entire project
- `writeLocatedException(OutputInterface, CompilerException): void`
- `writeStackTrace(OutputInterface, Throwable): void`
- `clearCache(): array` : clear all build caches
- `getOutputDirectory(): string`
- `getHealthCheck(): ModuleHealthCheckInterface` : cache + output + source dir checks

## Dependencies

- **Compiler** (`CompilerFacade`) : Phel-to-PHP compilation, environment init
- **Command** (`CommandFacade`) : source/output directories, error formatting

## Structure

```
Build/
├── Application/        compilers, extractors, cache clearer, health check
├── Domain/             Cache/, Compile/, Extractor/, IO/ (interfaces + value objects)
├── Infrastructure/     Cache/, Command/, IO/ (concrete cache + file IO + CLI)
└── Gacela files        BuildFacade, BuildFactory, BuildConfig, BuildProvider
```

## Key Constraints

- Two-level caching: namespace extraction + compiled code
- `TopologicalNamespaceSorter` ensures correct compilation order
- `BuildOptions` controls source maps and cache behavior
- Auto-detects main namespace from `core.phel` or `main.phel`
- Namespace extractors prune `<dest_dir>/` from recursive scans to avoid shadowing
