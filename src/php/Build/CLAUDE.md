# Build Module

Core build orchestrator: compiles Phel projects to PHP with dependency resolution and caching.

## Gacela Pattern

- **Facade**: `BuildFacade` implements `BuildFacadeInterface`
- **Factory**: `BuildFactory` extends `AbstractFactory<BuildConfig>`
- **Config**: `BuildConfig` — cache settings, paths to ignore, temp/cache dirs, entry point detection
- **Provider**: `BuildProvider` — injects `CompilerFacade` (`FACADE_COMPILER`) and `CommandFacade` (`FACADE_COMMAND`)

## Public API (Facade)

- `enableBuildMode()` / `disableBuildMode()` — toggle build mode flag
- `getNamespaceFromFile(string $filename): NamespaceInformation` — extract namespace from file
- `getNamespaceFromDirectories(array $dirs): array` — extract all namespaces from directories
- `getDependenciesForNamespace(array $dirs, array $ns): array` — topologically sorted dependencies
- `compileFile(string $src, string $dest): CompiledFile` — compile single file to PHP
- `evalFile(string $src): CompiledFile` — evaluate without writing output
- `compileProject(BuildOptions $options): array` — compile entire project
- `writeLocatedException(OutputInterface $output, CompilerException $e): void`
- `writeStackTrace(OutputInterface $output, Throwable $e): void`
- `clearCache(): array` — clear all build caches

## Dependencies

- **Compiler** (`CompilerFacade`) — Phel-to-PHP compilation, environment init
- **Command** (`CommandFacade`) — source/output directories, error formatting

## Structure

```
Build/
├── Application/        ProjectCompiler, FileCompiler, FileEvaluator, NamespaceExtractor, CacheClearer
├── Domain/             Interfaces + value objects (NamespaceInformation, CompiledFile, BuildOptions)
├── Infrastructure/     SystemFileIo, PhpNamespaceCache, CompiledCodeCache, TopologicalNamespaceSorter
└── Gacela files        BuildFacade, BuildFactory, BuildConfig, BuildProvider
```

## Key Constraints

- Two-level caching: namespace extraction cache + compiled code cache
- `TopologicalNamespaceSorter` ensures correct compilation order
- `CachedNamespaceExtractor` decorates `NamespaceExtractor` with caching
- `BuildOptions` controls source maps and cache behavior
- Auto-detects main namespace from `core.phel` or `main.phel`
