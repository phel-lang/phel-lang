# Compiler Module

Core compilation pipeline: Phel source -> tokens -> AST -> analyzed nodes -> PHP code.

## Gacela Pattern

- **Facade**: `CompilerFacade` implements `CompilerFacadeInterface`
- **Factory**: `CompilerFactory` extends `AbstractFactory<CompilerConfig>`
- **Config**: `CompilerConfig` — `assertsEnabled()` flag
- **Provider**: `CompilerProvider` — injects `FilesystemFacade` (`FACADE_FILESYSTEM`)

## Public API (Facade)

**Compilation & Evaluation**
- `compile(string $phelCode, CompileOptions): EmitterResult`
- `compileForCache(string $phelCode, CompileOptions): EmitterResult`
- `compileForm(TypeInterface|...|null $form, CompileOptions): EmitterResult`
- `eval(string $phelCode, CompileOptions): mixed`
- `evalForm(TypeInterface|...|null $form, CompileOptions): mixed`

**Pipeline Phases**
- `lexString(string $code, ...): TokenStream`
- `parseNext(TokenStream): ?NodeInterface` / `parseAll(TokenStream): FileNode`
- `read(NodeInterface $parseTree): ReaderResult`
- `analyze(TypeInterface|...|null $x, NodeEnvironmentInterface $env): AbstractNode`

**Macros**
- `macroexpand1($form)` / `macroexpand($form)`

**Environment**
- `initializeGlobalEnvironment()` / `resetGlobalEnvironment()`
- `getNamespaceEnvironmentData(string $ns): array` / `restoreNamespaceEnvironmentData(string $ns, array $data): void`

**Utility**
- `encodeNs(string $namespace): string`
- `hasBalancedParentheses(string $code): bool`

## Dependencies

- **Filesystem** (`FilesystemFacade`) — file I/O operations
- **Lang** (`TypeInterface`) — Phel types
- **Printer** — readable output
- **Config** (`PhelConfig`) — configuration

## Phase Pipeline

| Phase | Class | Input | Output |
|-------|-------|-------|--------|
| Lexer | `Application/Lexer` | Phel source string | `TokenStream` |
| Parser | `Application/Parser` | `TokenStream` | `FileNode` (parse tree) |
| Reader | `Application/Reader` | `NodeInterface` | `ReaderResult` (Phel data) |
| Analyzer | `Application/Analyzer` | Phel data | `AbstractNode` (AST) |
| Emitter | Domain/Emitter/ | `AbstractNode` | `EmitterResult` (PHP code) |

## Structure

```
Compiler/
├── Application/        Analyzer, CodeCompiler, EvalCompiler, Lexer, Parser, Reader, MacroExpander, Munge
├── Domain/
│   ├── Analyzer/       AST nodes, special form handlers, environments
│   ├── Compiler/       CodeCompilerInterface, EvalCompilerInterface
│   ├── Emitter/        OutputEmitter, FileEmitter, StatementEmitter, node emitters
│   ├── Evaluator/      InMemoryEvaluator, RequireEvaluator
│   ├── Exceptions/     CompilerException, AbstractLocatedException, ErrorCode
│   ├── Lexer/          Token, TokenStream
│   ├── Parser/         NodeInterface, ExpressionParserFactory
│   └── Reader/         ReaderInterface, QuasiquoteTransformer
├── Infrastructure/     CompileOptions, GlobalEnvironmentSingleton
└── Gacela files
```

## Key Constraints

- Never bypass a phase — each consumes only output of the previous
- Analyzer nodes must carry `NodeEnvironment` with correct context
- Emitter must handle every node type — missing cases must throw, not silently skip
- Special forms are registered centrally — no ad-hoc handling in the analyzer loop
- Source locations must propagate through all phases for error reporting
- `GlobalEnvironmentSingleton` manages the single global compile-time environment
