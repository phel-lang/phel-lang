# Shared Module

Contract layer: facade interfaces and cross-cutting constants for inter-module communication.

## No Gacela Pattern

This is a **pure contract module** : no Facade, Factory, or DependencyProvider. It defines the interfaces that other modules implement.

## Facade Interfaces (8 total)

Located in `Facade/` subdirectory. Each module implements its corresponding interface:

| Interface | Implemented By | Key Methods |
|-----------|---------------|-------------|
| `CompilerFacadeInterface` | Compiler | `eval()`, `compile()`, `lexString()`, `analyze()`, `macroexpand()` |
| `BuildFacadeInterface` | Build | `compileFile()`, `compileProject()`, `getNamespaceFromFile()` |
| `RunFacadeInterface` | Run | `runNamespace()`, `eval()`, `structuredEval()`, `loadPhelNamespaces()` |
| `CommandFacadeInterface` | Command | `writeLocatedException()`, `getAllPhelDirectories()`, `getSourceDirectories()` |
| `ConsoleFacadeInterface` | Console | `getVersion()`, `runConsole()` |
| `FormatterFacadeInterface` | Formatter | `format()` |
| `InteropFacadeInterface` | Interop | `generateExportCode()` |
| `ApiFacadeInterface` | Api | `getPhelFunctions()`, `replComplete()`, `replCompleteWithTypes()` |

## Constants

- **CompilerConstants** : `PHEL_CORE_NAMESPACE = 'phel.core'`
- **BuildConstants** : `BUILD_MODE = '*build-mode*'`
- **ReplConstants** : `REPL_MODE = '*repl-mode*'`

## Exception Classes (Exceptions/)

Moved from `Phel\Compiler\Domain\*` for cross-module access:

- `CompilerException` : wraps `AbstractLocatedException` with `CodeSnippet`
- `AbstractLocatedException` : base for located errors with `SourceLocation` and `ErrorCode`
- `ErrorCode` enum : `PHEL001..PHEL310` for analyzer/parser/reader/lexer errors
- `FileException` : file/directory failures (`canNotCreateFile`, etc.)
- `CompiledCodeIsMalformedException` : wraps `ParseError` from compiled PHP `eval()`

## Parser Model (Parser/)

- `ReadModel/CodeSnippet` : `SourceLocation` start/end + raw source string. Pure data, no Parser/Compiler imports.

## Printer

`Printer/` holds the readable/non-readable printer used by REPL output, eval-result serialisation, exception args, and `__toString()` of `Phel\Lang\AbstractType`. Pure utility : no module state, no I/O wiring : so consumers `new` it directly (`Printer::readable()` / `Printer::nonReadable()`) without crossing a module boundary.

| Class | Purpose |
|-------|---------|
| `Printer` | Factory + dispatcher over `TypePrinter/` strategies |
| `PrinterInterface` | Single `print($x): string` method |
| `TypePrinter/*` | One per Phel/PHP type; `WithColorTrait` for ANSI variants |

## Utility Classes

- **ColorStyle**: ANSI terminal colors (`green()`, `yellow()`, `blue()`, `red()`). Factory: `withStyles()` / `noStyles()`
- **ResourceUsageFormatter**: `resourceUsageSinceStartOfRequest()` returns `Time: HH:MM:SS.mmm, Memory: X.XX MB` (used by `run --with-time`, `test`, `build`)
- **Munge**: namespace/symbol encoder; `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`, `canonicalNs()`, `displayNs()`. Pure stateless. Used by Compiler and all modules resolving namespaces against the runtime registry

## Dependencies

None outward. Imports types from other modules only in interface signatures (Compiler exceptions, Lang types, Build value objects, etc.).

## Used By

**Every module** depends on Shared for its facade interface contract. This enables dependency inversion : modules depend on interfaces, not implementations.

## Key Constraints

- Remains a leaf dependency: defines contracts, never implements business logic
- New modules require adding their `FacadeInterface` here
- Facade interfaces import domain types only as needed; keep imports minimal
- Exceptions are cross-module: defined here, thrown/caught everywhere
