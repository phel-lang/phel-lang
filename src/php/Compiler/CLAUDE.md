# Compiler Module

Core compilation pipeline: Phel source to tokens to AST to analyzed nodes to PHP code.

## Gacela Pattern

- **Facade**: `CompilerFacade` implements `CompilerFacadeInterface`
- **Factory**: `CompilerFactory` extends `AbstractFactory<CompilerConfig>`
- **Config**: `CompilerConfig` exposes `assertsEnabled()`, `warnDeprecationsEnabled()`
- **Provider**: `CompilerProvider` injects `FilesystemFacade` via `FACADE_FILESYSTEM`

## Public API (Facade)

| Category | Methods |
|----------|---------|
| Compilation | `compile(string, CompileOptions): EmitterResult`, `compileForCache(...)`, `compileForm(mixed, CompileOptions): EmitterResult` |
| Evaluation | `eval(string, CompileOptions): mixed`, `evalForm(mixed, CompileOptions): mixed` |
| Pipeline | `lexString(string, ...): TokenStream`, `parseNext(TokenStream): ?NodeInterface`, `parseAll(TokenStream): FileNode`, `read(NodeInterface): ReaderResult`, `analyze(mixed, NodeEnvironmentInterface): AbstractNode` |
| Macros | `macroexpand1(mixed): mixed`, `macroexpand(mixed): mixed` |
| Environment | `initializeGlobalEnvironment(): void`, `resetGlobalEnvironment(): void`, `isGlobalEnvironmentInitialized(): bool`, `getGlobalEnvironment(): GlobalEnvironmentInterface`, `initializeNewGlobalEnvironment(): GlobalEnvironmentInterface`, `setGlobalEnvironment(GlobalEnvironmentInterface): void` |
| Namespace state | `getNamespaceEnvironmentData(string): array`, `restoreNamespaceEnvironmentData(string, array): void` |
| Debugging | `enableDebugLineTap(?string, string): void`, `disableDebugLineTap(): void` |
| Utility | `encodeNs(string): string` (PHP-form via `Munge::encodePhpNs`), `hasBalancedParentheses(string): bool` |

## Dependencies

- **Filesystem**: `FilesystemFacade` for file I/O
- **Shared**: `Munge`, `Printer`, exceptions

## Phase Pipeline

| Phase | Input | Output |
|-------|-------|--------|
| Lexer | Phel source string | `TokenStream` |
| Parser | `TokenStream` | `FileNode` (parse tree) |
| Reader | `NodeInterface` | `ReaderResult` (Phel data) |
| Analyzer | Phel data | `AbstractNode` (AST with `NodeEnvironment`) |
| Simplifier | `AbstractNode` | `AbstractNode` (optimized) |
| Emitter | `AbstractNode` | `EmitterResult` (PHP code) |

**Simplification pass** (runs after `ConstantFolder`): drops pure non-tail expressions from `(do ...)` via `PureExpressionDetector`; inlines calls at opt level >= 2 via `CallInliner` (delegates purity to `ConstantFolder` for known calls, `SymbolicPurityDetector` for structural checks). `^:pure` metadata opts a `defn` into inlining trust (author responsibility for correctness).

## Structure

```
Compiler/
├── Application/           Analyzer, CodeCompiler, EvalCompiler, Lexer, Parser, Reader, 
│                         GlobalEnvironmentManager, MacroExpander, NamespaceEnvironmentSerializer
├── Domain/
│   ├── Analyzer/         AST nodes, ConstantFolder, TypeAnalyzer, special form handlers,
│   │                     GlobalEnvironmentRegistry/Interface, SymbolSuggestionProvider
│   ├── Compiler/         CodeCompilerInterface, EvalCompilerInterface
│   ├── Emitter/          OutputEmitter, FileEmitter, StatementEmitter, *Specialization classes,
│   │                     NodeEmitter/ (per-AST emitters; Specialized/ holds the CallEmitter families)
│   ├── Evaluator/        InMemoryEvaluator, RequireEvaluator
│   ├── Lexer/            TokenStream (Token + parse-tree nodes live in Phel\Shared\Parser\Node)
│   ├── Parser/           ExpressionParserFactory (produces Shared\Parser\Node\* parse tree);
│   │                     ExpressionParser/ sub-parsers (Atom, String, List, Quote, Meta, ReaderConditional)
│   └── Reader/           ReaderInterface, QuasiquoteTransformer, ExpressionReaderFactory
├── Infrastructure/       GlobalEnvironmentSingleton (ABI shim for generated PHP), DebugLineTap
└── Gacela files          CompilerFacade, CompilerFactory, CompilerConfig, CompilerProvider
```

## Key Constraints

- Never bypass a phase; each consumes only output of the previous
- Analyzer nodes must carry `NodeEnvironment` with correct context
- Emitter must handle every node type; missing cases throw, not silently skip
- Special forms registered centrally; no ad-hoc handling in analyzer loop
- Source locations must propagate through all phases for error reporting
- `GlobalEnvironmentSingleton` FQN is baked into cached `.phel` files; do not rename
- `LoadEmitter` bakes `Phel\Lang\LoadClasspath::class` (the `(load ...)` classpath store) into generated PHP; the class lives in `Lang` because its state is the `*load-classpath*` slot in `Lang\Registry`. Do not rename.

## Type-Specialized Emission

Analyzer tracks param and return types via `ParamTypeInferrer`, `ReturnTypeInferrer`, grafting `:tag` meta onto binding symbols. Call-site *eligibility* lives on `*Specialization` classes (`NumericOperationSpecialization`, `TypePredicateSpecialization`, `TypedValueSpecialization`, `TypedCollectionMethodSpecialization`, `AssocConjSpecialization`, `AtomMethodSpecialization`, `NilAndBooleanCheckSpecialization`); `CallSpecialization` aggregates them. The matching PHP *emission* lives on per-family collaborators under `NodeEmitter/Specialized/` (one `*CallEmitter implements SpecializedCallEmitterInterface` per eligibility class); `CallEmitter` builds them once and dispatches by looping `tryEmit()` over them before the generic call path. Family predicates are disjoint, so chain order between families is not significant. Add a new specialization family as a `*Specialization` eligibility class, register it in `CallSpecialization::isSpecialized()`, and add the matching `Specialized/*CallEmitter` to `CallEmitter`'s ordered list. Contract: propagate only analyzer-published types, never fabricate.

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`. `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) both read/write the same slot. `GlobalEnvironmentSingleton` is retained as ABI shim; emitter writes literal calls to `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` into generated PHP (baked into cached `.phel` files).

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `src/php/Shared/CLAUDE.md`). Two encoders at different boundaries: `encodePhpNs` (backslash form, PHP `namespace` declarations and class FQNs) and `encodeRegistryKey` (dot form, Phel registry lookups). Analyzer uses dot-separated namespace internally; emission routes through `encodePhpNs` for PHP output.
