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

## Generated-Class Attributes & Typed Signatures

`DefStructEmitter` and `DefInterfaceEmitter` read per-symbol metadata to enrich the PHP they generate, sharing `PhpAttributeEmitterTrait` (tag + `:php/attr` reading) and the pure `Phel\Shared\PhpAttributeRenderer`. A `:tag` value can be a bare symbol/string (verbatim, so `?int`/`self`/`\DateTime` pass through), a list (union → `a|b`), or a vector (intersection → `a&b`):

- `defstruct`: a field's `^{:tag <type>}` emits a typed property (`protected int $id;`); `^{:php/attr [...]}` on the struct name (class-level) or a field (property-level) emits PHP 8 attributes. `^{:php/json true}` on the struct name implements `\JsonSerializable` (emitting a `jsonSerialize()` that returns the field map) and `^{:php/stringable true}` declares `\Stringable`. `^:php/readonly` on the struct name emits `readonly` typed properties (untagged fields default to `readonly mixed`) and a constructor-rebuilding `put()` override so persistent updates keep working; the class stays a plain `final class` (it cannot be a PHP `readonly class` because `AbstractPersistentStruct` is not readonly). A `:php` marker opens a block of bare PHP magic methods (`__invoke`/`__toString`/`__get`) emitted on the class with no interface (`PhpBlockAnalyzer`, carried as a `DefStructInterface` with empty name that `DefStructEmitter` drops from `implements`); a custom `__invoke` must be 1-arg or variadic (`PhpBlockAnalyzer` rejects bad arity).
- `definterface`: a method's arg `^{:tag <type>}` emits a typed param and the method name's `:tag` the return type; `^{:php/attr [...]}` on the interface name, a method, or a method **parameter** emits interface-/method-/parameter-level attributes (the parameter form is emitted inline: `show(#[\Autowire] string $repo)`). A trailing `:php/const` block declares typed class constants: `:php/const (^{:tag int} MAX 100)` → `const int MAX = 100;` (value must be an int/float/string/bool/nil literal; `DefInterfaceSymbol`/`PhpClassConst`/`DefInterfaceEmitter`). The `definterface` macro only wraps method forms in Phel fns; the const block passes through to `definterface*` only.
- `defenum` (`defenum*`, `DefEnumSymbol`/`DefEnumNode`/`DefEnumEmitter`): emits a native PHP `enum`; cases are keyword-named with an optional `int`/`string` value (all-or-none → backed vs pure enum), and `^{:php/attr [...]}` on the enum name emits class-level attributes. After the cases, an optional implementations tail (interface symbols + their methods, and a `:php` block of plain/magic methods) is parsed via the shared `InterfaceImplementationsAnalyzer` (also used by `defstruct`) and emitted as `implements` + methods via `MethodEmitter`. Guarded by `enum_exists`.

The interface + `:php`-block parsing shared by `defstruct` and `defenum` lives in `InterfaceImplementationsAnalyzer` (interface symbols are reflection-validated; `:php` blocks become a `DefStructInterface` with an empty name). `PhpBlockAnalyzer::analyze` takes an `enforceInvokeArity` flag (true only for structs, whose map `__invoke` constrains arity).

`^{:php/doc <str|[str...]>}` on any of those names/fields/methods emits a PHPDoc block (one-line string or multi-line list/vector) above the construct, so phpstan/psalm see generated classes as typed.

`^:php/override` on a method (defstruct/defenum interface impls, definterface methods) is sugar for `#[\Override]` (PHP 8.3); `PhpAttributeEmitterTrait::phpAttributeLines` renders it ahead of any explicit `:php/attr` lines. Struct/enum inline method impls now emit method-level `:php/attr`/`:php/doc`/`^:php/override` too (previously only definterface methods did).

All opt-in; untagged forms are byte-identical to before. Export wrappers carry the same `:php/attr` via `Interop`'s `CompiledPhpMethodBuilder` (see `src/php/Interop/CLAUDE.md`).

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`. `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) both read/write the same slot. `GlobalEnvironmentSingleton` is retained as ABI shim; emitter writes literal calls to `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` into generated PHP (baked into cached `.phel` files).

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `src/php/Shared/CLAUDE.md`). Two encoders at different boundaries: `encodePhpNs` (backslash form, PHP `namespace` declarations and class FQNs) and `encodeRegistryKey` (dot form, Phel registry lookups). Analyzer uses dot-separated namespace internally; emission routes through `encodePhpNs` for PHP output.
