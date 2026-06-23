# Shared Module

Contract layer: facade interfaces, constants, cross-module value objects, and pure stateless utilities. No Gacela pattern; no outward dependencies (other modules appear only in interface signatures).

## Facade Interfaces

`Facade/` holds the cross-module facade contracts: Compiler, Build, Run, Command, Console, Formatter, Interop, Api. Modules import these instead of concrete facades; this enables dependency inversion. A facade interface belongs here once another module consumes it; keep its imports minimal.

## Constants

- `CompilerConstants.PHEL_CORE_NAMESPACE = 'phel.core'`, `DEFAULT_SOURCE = 'string'` (the `lexString` source label; `CompilerFacadeInterface` defaults to it so it does not reference the `Application\Lexer` concrete)
- `BuildConstants.BUILD_MODE = '*build-mode*'`; `ReplConstants.REPL_MODE = '*repl-mode*'`
- `CompileOptions.DEFAULT_SOURCE`, `DEFAULT_STARTING_LINE`, `DEFAULT_ENABLE_SOURCE_MAPS`, `DEFAULT_EMIT_ONLY`

## Exceptions (`Exceptions/`)

- `CompilerException`: wraps `AbstractLocatedException` with `CodeSnippet`
- `AbstractLocatedException`: base for located errors with `SourceLocation` and `ErrorCode` (enum, PHEL001-PHEL310: analyzer, parser, reader, lexer)
- `FileException` (file/directory ops), `CompiledCodeIsMalformedException` (wraps PHP `eval()` parse errors)
- `Exceptions/Hint/`: pure error→hint mappers (`ExceptionHintInterface` + `UndefinedSymbolHint`, `ArgumentCountHint`, `NotCallableHint`) and `ExceptionHintResolver` (returns the first applicable hint, unwrapping one `getPrevious()` level). Shared so both the REPL formatter (Run) and the CLI command error writer (Command) surface the same guidance

## Value Objects

- `NamespaceInformation`: pure `final readonly` DTO (`file`, `namespace`, `dependencies`, `isPrimaryDefinition`) produced by Build, consumed across Build/Run/Interop. Lives here so the Shared facade contracts don't back-reference a foreign module's `Domain`.
- `Eval/`: pure `final readonly` VOs for eval outcomes: `EvalResult` (`success()`/`incomplete()`/`failure()` named constructors), `EvalError`, `StackFrame`. Returned by `RunFacadeInterface::structuredEval()`, consumed by Nrepl/Watch; producing orchestration lives in `Run\Application\StructuredEvaluator`, so these stay logic-free.

## Parser Model

- `Parser/ReadModel/CodeSnippet`: `SourceLocation` (start/end) and source string; pure data.
- `Parser/Node/*`: parse-tree value objects (`FileNode`, `ListNode`, `SymbolNode`, `MetaNode`, trivia nodes, ...) plus the lexer `Token`. De-facto AST contract consumed by Formatter, Lint, Api; Compiler produces them. Living here removes `CompilerFacadeInterface -> Compiler\Domain` references.

## Printer

Sub-module in `Printer/` (see `Printer/CLAUDE.md`): stateless strategy-pattern printer; consumers instantiate directly.

## Utility Classes

- `Munge`: namespace/symbol encoding; `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`, `canonicalNs()`, `displayNs()`
- `ColorStyle`: ANSI colors; factories `withStyles()`, `noStyles()`
- `ScalarCoercion`: coerce loosely-typed config `mixed` to a scalar with a default; `toString()`, `toInt()`, `toFloat()`
- `ResourceUsageFormatter`: "Time: HH:MM:SS.mmm, Memory: X.XX MB" snapshot
- `PhelProjectDirectory`: manages `.phel/` directory; respects `PHEL_DIR` env var and `PhelConfig::withPhelDir()`
- `VersionFinder`: pure version-string builder from explicit git inputs; no I/O. `VersionResolver`: gathers ambient version inputs (git working copy, Composer `InstalledVersions`, build-time `.phel-release.php`/`OFFICIAL_RELEASE`) and calls `VersionFinder`. Both Console and Run consume it directly, so neither owns version-detection wiring
- `CompileOptions`: source maps, emit-only mode, optimization levels
- `SourceMap/VLQ`: pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); consumed by Compiler's `SourceMapGenerator`/`SourceMapConsumer`
- `SourceMap/SourceMapSiblings`: naming convention for the `<file>.php.map` + `<file>.phel` artifacts next to built files. Written by Build (`FileCompiler`, `SecondaryFileHarvester`), read by Command (`SourceMapExtractor`)
- `SourceMap/BuiltFilePreamble`: the fixed `<?php declare(strict_types=1);` line before generated code in built files; `prepend()` for the writer (`FileCompiler`), `codeStartLine()` for the reader (`SourceMapExtractor`)
- `SourceMap/InlineSourceMapComments`: `// `/`// ;;` metadata comment prefixes for inline source maps in eval'd code. Written by Compiler's `EmitterResult`, parsed by `SourceMapExtractor` and `EvaluatedCodeException`
- `PhpAttributeRenderer`: renders `^{:php/attr ...}` metadata specs into PHP 8 attribute source lines (`#[\ORM\Column(length: 255)]`). Accepts a bare keyword (`:ORM/Entity`), a single spec vector (`[:ORM/Column {:length 255}]`), or a vector of specs (`[[:ORM/Id] [:ORM/Column]]`). Consumed by `DefStructEmitter`/`DefInterfaceEmitter` and the Interop export generator

## Key Constraints

- Leaf dependency: contracts and utilities only, never business logic
- Exceptions are cross-module: thrown and caught everywhere
- Utilities remain stateless: safe to instantiate without module context
