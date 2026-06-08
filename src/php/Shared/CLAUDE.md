# Shared Module

Contract layer: facade interfaces, constants, and cross-module utilities; pure stateless helpers and exception registry.

## No Gacela Pattern

Pure contract module; no Facade, Factory, or DependencyProvider. Defines interfaces other modules implement.

## Facade Interfaces (8)

Located in `Facade/` subdirectory. Each module implements one:

| Interface | Module | Key Methods |
|-----------|--------|-------------|
| `CompilerFacadeInterface` | Compiler | `eval()`, `compile()`, `lexString()`, `analyze()`, `macroexpand()` |
| `BuildFacadeInterface` | Build | `compileFile()`, `compileProject()`, `getNamespaceFromFile()` |
| `RunFacadeInterface` | Run | `runNamespace()`, `eval()`, `structuredEval()`, `loadPhelNamespaces()` |
| `CommandFacadeInterface` | Command | `writeLocatedException()`, `getAllPhelDirectories()`, `getSourceDirectories()` |
| `ConsoleFacadeInterface` | Console | `getVersion()`, `runConsole()` |
| `FormatterFacadeInterface` | Formatter | `format()` |
| `InteropFacadeInterface` | Interop | `generateExportCode()` |
| `ApiFacadeInterface` | Api | `getPhelFunctions()`, `replComplete()`, `replCompleteWithTypes()` |

## Constants

- `CompilerConstants.PHEL_CORE_NAMESPACE = 'phel.core'`, `DEFAULT_SOURCE = 'string'` (the `lexString` source label; `CompilerFacadeInterface` defaults to it so it no longer references the `Application\Lexer` concrete)
- `BuildConstants.BUILD_MODE = '*build-mode*'`
- `ReplConstants.REPL_MODE = '*repl-mode*'`
- `CompileOptions.DEFAULT_SOURCE = 'string'`, `DEFAULT_STARTING_LINE = 1`, `DEFAULT_ENABLE_SOURCE_MAPS = true`, `DEFAULT_EMIT_ONLY = false`

## Exceptions

Located in `Exceptions/`:

- `CompilerException`: wraps `AbstractLocatedException` with `CodeSnippet`
- `AbstractLocatedException`: base for located errors with `SourceLocation` and `ErrorCode`
- `ErrorCode`: enum for PHEL001-PHEL310 error codes (analyzer, parser, reader, lexer)
- `FileException`: file/directory operations
- `CompiledCodeIsMalformedException`: wraps PHP `eval()` parse errors

## Value Objects

- `NamespaceInformation`: pure `final readonly` DTO (`file`, `namespace`, `dependencies`, `isPrimaryDefinition`) produced by Build, consumed across Build/Run/Interop and returned by the Build/Run facade interfaces. Lives here so the Shared facade contracts no longer back-reference a foreign module's `Domain`.
- `Eval/`: pure `final readonly` VOs describing an eval outcome — `EvalResult` (`success()`/`incomplete()`/`failure()` named constructors), `EvalError`, `StackFrame`. Returned by `RunFacadeInterface::structuredEval()` and consumed by Nrepl/Watch; the producing orchestration lives in `Run\Application\StructuredEvaluator`, so these stay logic-free. Lives here so the Run facade contract no longer exposes `Run\Domain`.

## Parser Model

- `Parser/ReadModel/CodeSnippet`: `SourceLocation` (start/end) and source string; pure data, no dependencies.
- `Parser/Node/*`: the parse-tree value objects (`FileNode`, `ListNode`, `SymbolNode`, `MetaNode`, trivia nodes, etc.) plus the lexer `Token`. Pure data consumed as a de-facto AST contract by Formatter, Lint, and Api; Compiler still produces them. Living here removes the `CompilerFacadeInterface -> Compiler\Domain` parse-tree references.

## Printer

Sub-module in `Printer/`: stateless, no I/O wiring. Entry point: `Printer::readable() | readableWithColor() | nonReadable()` then `print($form): string`. Consumers instantiate directly (no module boundary).

Strategy pattern via `TypePrinter/`: one class per Phel/PHP type; `WithColorTrait` for ANSI support.

## Utility Classes

- `ColorStyle`: ANSI colors (green, yellow, blue, red); factories: `withStyles()`, `noStyles()`
- `Munge`: namespace/symbol encoding; `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`, `canonicalNs()`, `displayNs()`
- `ResourceUsageFormatter`: returns "Time: HH:MM:SS.mmm, Memory: X.XX MB" snapshot
- `PhelProjectDirectory`: manages `.phel/` directory; respects `PHEL_DIR` env var and `PhelConfig::setPhelDir()`
- `VersionFinder`: pure version-string builder from explicit git inputs (`tagCommitHash`, `currentCommit`, `isOfficialRelease`); no I/O
- `VersionResolver`: gathers the ambient version inputs (git working copy, Composer `InstalledVersions`, build-time `.phel-release.php`/`OFFICIAL_RELEASE`) and returns `VersionFinder::getVersion()`. Both Console and Run consume it directly, so neither owns version-detection wiring
- `CompileOptions`: constants for source maps, emit-only mode, optimization levels
- `SourceMap/VLQ`: pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); stateless, no module deps. Consumed by Compiler's `SourceMapGenerator`/`SourceMapConsumer`
- `PhpAttributeRenderer`: renders `^{:php/attr ...}` metadata specs into PHP 8 attribute source lines (`#[\ORM\Column(length: 255)]`); pure, stateless. Accepts a bare keyword (`:ORM/Entity`), a single spec vector (`[:ORM/Column {:length 255}]`, first element is the name keyword), or a vector of specs (`[[:ORM/Id] [:ORM/Column]]`). Consumed by `DefStructEmitter`/`DefInterfaceEmitter` and the Interop export generator

## Dependencies

None outward. Imports from other modules only in interface signatures (Compiler exceptions, Lang types, etc.).

## Used By

Every module imports its facade interface from here. Enables dependency inversion.

## Key Constraints

- Leaf dependency: contracts and utilities only, never business logic
- New modules must add `FacadeInterface` here
- Facade interfaces keep imports minimal
- Exceptions are cross-module: thrown and caught everywhere
- Utilities remain stateless: safe to instantiate without module context
