# Compiler Module

Core compilation pipeline: Phel source -> tokens -> AST -> analyzed nodes -> PHP code.

## Gacela Pattern

- **Facade**: `CompilerFacade` implements `CompilerFacadeInterface`
- **Factory**: `CompilerFactory` extends `AbstractFactory<CompilerConfig>`
- **Config**: `CompilerConfig` : `assertsEnabled()` flag
- **Provider**: `CompilerProvider` : injects `FilesystemFacade` (`FACADE_FILESYSTEM`)

## Public API (Facade)

**Compilation & Evaluation**
- `compile(string, CompileOptions): EmitterResult`
- `compileForCache(string, CompileOptions): EmitterResult`
- `compileForm(TypeInterface|...|null, CompileOptions): EmitterResult`
- `eval(string, CompileOptions): mixed`
- `evalForm(TypeInterface|...|null, CompileOptions): mixed`

**Pipeline Phases**
- `lexString(string): TokenStream`
- `parseNext(TokenStream): ?NodeInterface`
- `parseAll(TokenStream): FileNode`
- `read(NodeInterface): ReaderResult`
- `analyze(TypeInterface|...|null, NodeEnvironmentInterface): AbstractNode`

**Macros**
- `macroexpand1(mixed): TypeInterface|...|null`
- `macroexpand(mixed): TypeInterface|...|null`

**Environment Management**
- `initializeGlobalEnvironment(): void`
- `resetGlobalEnvironment(): void`
- `isGlobalEnvironmentInitialized(): bool`
- `getGlobalEnvironment(): GlobalEnvironmentInterface`
- `initializeNewGlobalEnvironment(): GlobalEnvironmentInterface`
- `setGlobalEnvironment(GlobalEnvironmentInterface): void`
- `getNamespaceEnvironmentData(string): array`
- `restoreNamespaceEnvironmentData(string, array): void`

**Debugging**
- `enableDebugLineTap(): void`
- `disableDebugLineTap(): void`

**Utility**
- `encodeNs(string): string` : PHP-form encoder via `Phel\Shared\Munge::encodePhpNs()`
- `hasBalancedParentheses(string): bool`

## Dependencies

- **Filesystem** (`FilesystemFacade`) : file I/O
- **Shared** : exceptions, printer, munge utility

## Phase Pipeline

| Phase | Class | Input | Output |
|-------|-------|-------|--------|
| Lexer | `Application/Lexer` | Phel source string | `TokenStream` |
| Parser | `Application/Parser` | `TokenStream` | `FileNode` (parse tree) |
| Reader | `Application/Reader` | `NodeInterface` | `ReaderResult` (Phel data) |
| Analyzer | `Application/Analyzer` | Phel data | `AbstractNode` (AST) |
| Simplifier | `Domain/Analyzer/TypeAnalyzer/Simplification/` | `AbstractNode` | `AbstractNode` (smaller) |
| Emitter | Domain/Emitter/ | `AbstractNode` | `EmitterResult` (PHP code) |

The simplification pass runs **after** the analyser-level `ConstantFolder`. It currently drops pure non-tail expressions from `(do ...)` bodies; purity is decided by `PureExpressionDetector`, which delegates `CallNode` checks to `ConstantFolder` so a call is "pure" only when the folder can statically compute its value (calls that would throw at runtime stay un-dropped).

`Simplification/` also hosts the call inliner (opt level >= 2, issues #2135, #2215, #2216): `InvokeSymbol` calls `CallInliner::tryInline()` for a `GlobalVarNode` callee. When the callee is a single-arity, non-variadic, non-recursive, non-memoised `defn` with a single pure body expression (body read from the `GlobalEnvironment` `defFnNode` side-table), the body is spliced at the call site with parameters replaced by the argument nodes and envs rebased onto the caller. The rebaser handles `Literal` / `LocalVar` / `GlobalVar` / `PhpVar` / `Call` / `If` plus vector / map / set literals (their elements rebased in expression context); any other node type aborts the inline. Pure arguments (`SymbolicPurityDetector`) substitute directly so they stay foldable; impure or multi-use arguments are bound to a fresh gensym `let` (via `BindingNode` + `LetNode`, then run through `LetSimplifier`) so each evaluates exactly once, left to right. Purity here is structural via `SymbolicPurityDetector` (a closed allowlist of side-effect-free `phel.core` / `php/` ops), unlike `PureExpressionDetector` which proves purity by full evaluation. A `defn` tagged `^:pure` opts into trust: `SymbolicPurityDetector` treats its calls as pure operators, and `CallInliner` skips the structural body-purity gate for it (the rebaser still aborts on unsupported node types). `^:pure` is an author assertion — mis-annotation is the author's responsibility. At opt level < 2 `tryInline` returns `null` immediately, so default output is unchanged.

## Structure

```
Compiler/
├── Application/        Analyzer, CodeCompiler, EvalCompiler, GlobalEnvironmentManager, Lexer,
│                       Parser, Reader, MacroExpander
├── Domain/
│   ├── Analyzer/       AST nodes, special form handlers, GlobalEnvironmentManagerInterface +
│   │                   GlobalEnvironmentRegistry
│   ├── Compiler/       CodeCompilerInterface, EvalCompilerInterface
│   ├── Emitter/        OutputEmitter, FileEmitter, StatementEmitter, node emitters
│   ├── Evaluator/      InMemoryEvaluator, RequireEvaluator
│   ├── Lexer/          Token, TokenStream
│   ├── Parser/         NodeInterface, ExpressionParserFactory
│   └── Reader/         ReaderInterface, QuasiquoteTransformer
├── Infrastructure/     CompileOptions, GlobalEnvironmentSingleton (ABI shim)
└── Gacela files        CompilerFacade, CompilerFactory, CompilerConfig, CompilerProvider
```

## Key Constraints

- Never bypass a phase : each consumes only output of the previous
- Analyzer nodes must carry `NodeEnvironment` with correct context
- Emitter must handle every node type : missing cases must throw, not silently skip
- Special forms are registered centrally : no ad-hoc handling in the analyzer loop
- Source locations must propagate through all phases for error reporting

## Inferred-type plumbing

The analyser already tracks param types (`ParamTypeInferrer`), return types (`ReturnTypeInferrer`), and grafts them onto each binding `Symbol`'s `:tag` meta. Emitters that want to specialise on a known type read that meta via:

- `LocalVarNode::getInferredType(): ?string` resolves the binding's tag by name walk through `NodeEnvironment::getLocals()`. Returns `null` for bindings without a tag.
- `GlobalVarNode::getMeta()` carries `arglists` / `tag` for `defn`s — `GlobalCallTarget::isGlobalFnCall()` and `ConstantFolder` consume it directly.
- `IterableTarget::isIterable` / `::isPhpArray` already consume the local tag for `foreach` / `apply` adapter skips.

Add new consumers by extending those predicates (or adding a sibling under `Compiler/Domain/Emitter/OutputEmitter/`). The contract is: never fabricate a type — propagate only what the analyser already published.

## Global Environment

State lives in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry` (process-wide static). The Application's `GlobalEnvironmentManager` and the infrastructure's `GlobalEnvironmentSingleton` both read/write the same slot.

`GlobalEnvironmentSingleton` is retained as an ABI shim: the emitter writes literal `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` calls into generated PHP. Cached `.phel` files depend on that exact FQN. All static methods forward to the registry.

## Namespace Encoding

Namespace and symbol encoding is owned by `Phel\Shared\Munge` (see `src/php/Shared/CLAUDE.md`). The compiler consumes it via `CompilerFactory::createMunge()` and exposes the PHP-form encoder through `CompilerFacade::encodeNs()`. Two encoders, used at different boundaries:

| Encoder | Form | Used by |
|---|---|---|
| `encodePhpNs` | backslash | PHP `namespace ...;` declaration, class FQNs, `BOUND_TO`, `(load ...)` filename derivation |
| `encodeRegistryKey` | dot | `\Phel::addDefinition` first arg, `\Phel::getDefinition` first arg, `\Phel::setVar` first arg, in-mem registry lookups |

`Munge::canonicalNs` and `Munge::displayNs` both translate backslash to dot : dot is the canonical form. The analyzer's internal namespace string (`GlobalEnvironment::$ns`, `definitions[$ns]`, `requireAliases[$ns]`) is dot-separated; PHP-emission paths route the same string through `encodePhpNs` when emitting PHP class FQNs / `namespace ...;` declarations.
