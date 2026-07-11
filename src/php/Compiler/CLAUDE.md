# Compiler Module

Core compilation pipeline: Phel source → tokens → AST → analyzed nodes → PHP code.

## Public API (Facade)

| Category | Methods |
|----------|---------|
| Compilation | `compile`, `compileForCache`, `compileForm` (return `EmitterResult`) |
| Evaluation | `eval(string, CompileOptions)`, `evalForm(mixed, CompileOptions)` |
| Pipeline | `lexString → TokenStream`, `parseNext → ?NodeInterface`, `parseAll → FileNode`, `read → ReaderResult`, `analyze(mixed, NodeEnvironmentInterface) → AbstractNode` |
| Macros | `macroexpand1`, `macroexpand` |
| Environment | `initializeGlobalEnvironment`, `resetGlobalEnvironment`, `isGlobalEnvironmentInitialized`, `getGlobalEnvironment`, `initializeNewGlobalEnvironment`, `setGlobalEnvironment` |
| Namespace state | `getNamespaceEnvironmentData`, `restoreNamespaceEnvironmentData` |
| Debugging | `enableDebugLineTap`, `disableDebugLineTap` |
| Utility | `encodeNs` (PHP-form via `Munge::encodePhpNs`), `hasBalancedParentheses` |

## Dependencies

- **Filesystem** — file I/O.
- **Config** — `PhelConfig` data model, wrapped by `CompilerConfig` (`assertsEnabled()`, `warnDeprecationsEnabled()`, `isIntermediateCacheEnabled()`, `getCacheDir()`).
- **Shared** — `Munge`, `Printer`, exceptions.

## Phase Pipeline

Lexer (source → `TokenStream`) → Parser (→ `FileNode` parse tree) → Reader (→ `ReaderResult` Phel data) → Analyzer (→ `AbstractNode` AST with `NodeEnvironment`) → Simplifier (→ optimized AST) → Emitter (→ `EmitterResult` PHP code).

- Lexer `Token` and parse-tree nodes live in `Phel\Shared\Parser\Node`; `ExpressionParserFactory` produces them (sub-parsers in `Domain/Parser/ExpressionParser/`).

### Simplification pass

Runs after `ConstantFolder` (in `Domain/Analyzer/TypeAnalyzer/Simplification/`):

- Drops pure non-tail expressions from `(do ...)` via `PureExpressionDetector`.
- Inlines calls at opt level >= 2 via `CallInliner` (purity from `ConstantFolder` for known calls, `SymbolicPurityDetector` for structural checks).
- `^:pure` metadata opts a `defn` into inlining trust (author owns correctness).

### Reader-result cache

Opt-in, off by default (`CompilerConfig::isIntermediateCacheEnabled()`). Wired only into `createCodeCompilerForCache` (build path), never the REPL path.

- `CodeCompiler` persists each source's read results via `Domain/Cache/ReaderResultCacheInterface`.
- Enabled → `Infrastructure/Cache/FileSystemReaderResultCache` (gzip'd `serialize` under `<cacheDir>/read-result/`, key = `md5(version|optLevel|source)`); else `NullReaderResultCache`.
- Warm hit skips lex/parse/read and replays each form's recorded read-phase gensym delta (`Domain/Cache/CachedReaderResult` = `ReaderResult` + delta) before analysis, so the shared `Symbol::gen()` counter follows the cold-compile trajectory.
- GOTCHA: replayed forms are deserialized Phel values, so anything used as a map key must compare by value, not identity (`Keyword::equals`/`Symbol::equals`) — else a cached keyword-keyed lookup silently misses on replay.
- Emitted PHP is stable for a given counter trajectory, but gensym names are process-global; a build mixing fresh compiles with compiled-code-cache hits can renumber them (pre-existing, independent of this cache).

## Key Constraints

- Never bypass a phase; each consumes only output of the previous.
- Analyzer nodes must carry `NodeEnvironment` with correct context.
- Emitter must handle every node type; missing cases throw, not silently skip.
- Special forms registered centrally; no ad-hoc handling in analyzer loop.
- Source locations must propagate through all phases for error reporting.
- Do NOT rename `GlobalEnvironmentSingleton` — its FQN is baked into cached `.phel` files.
- Do NOT rename `LoadEmitter`'s `Phel\Lang\LoadClasspath::class` (the `(load ...)` classpath store) — `LoadEmitter` bakes its FQN into generated PHP. It lives in `Lang` because its state is the `*load-classpath*` slot in `Lang\Registry`.

## Type-Specialized Emission

Analyzer tracks param/return types via `ParamTypeInferrer` and `ReturnTypeInferrer`, grafting `:tag` meta onto binding symbols. Contract: propagate only analyzer-published types, never fabricate.

Two halves, by family:

- **Eligibility** — `*Specialization` classes in `Domain/Emitter/OutputEmitter/`: `NumericOperationSpecialization`, `TypePredicateSpecialization`, `TypedValueSpecialization`, `TypedCollectionMethodSpecialization`, `AssocConjSpecialization`, `GetInSpecialization`, `AtomMethodSpecialization`, `NilAndBooleanCheckSpecialization`, `ReduceSpecialization`. `CallSpecialization` aggregates them.
- **Emission** — one `Specialized/*CallEmitter implements SpecializedCallEmitterInterface` per family under `NodeEmitter/Specialized/`. `CallEmitter` builds them once and dispatches by looping `tryEmit()` before the generic call path.

Family predicates are disjoint, so chain order between families is not significant.

To add a family: write a `*Specialization` eligibility class, register it in `CallSpecialization::isSpecialized()`, and add the matching `Specialized/*CallEmitter` to `CallEmitter`'s ordered list.

GOTCHA: only eager core fns can be lowered to a native loop. `reduce` (3-arity) qualifies. `map`/`filter` do NOT — they return a `LazySeq` over a `Seq::map`/`Seq::filter` generator and `copy-meta` the source; an eager `foreach` lowering would change the return type, break infinite/expensive seqs, and shift side-effect timing. They also gain little: `f` is handed to the generator once, so there is no per-element registry dispatch to remove.

## Generated-Class Attributes & Typed Signatures

`DefStructEmitter`, `DefInterfaceEmitter`, and `DefEnumEmitter` read per-symbol metadata to enrich generated PHP. They share `PhpAttributeEmitterTrait` (tag + `:php/attr` reading) and the pure `Phel\Shared\PhpAttributeRenderer`. All opt-in; untagged forms are byte-identical to before.

`:tag` value forms: bare symbol/string = verbatim (`?int`/`self`/`\DateTime` pass through); list = union (`a|b`); vector = intersection (`a&b`).

### defstruct (`DefStructEmitter`)

- Field `^{:tag <type>}` → typed property (`protected int $id;`).
- `^{:php/attr [...]}` on struct name (class-level) or field (property-level) → PHP 8 attributes.
- `^{:php/json true}` on struct name → implements `\JsonSerializable` (`jsonSerialize()` returns the field map).
- `^{:php/stringable true}` on struct name → declares `\Stringable`.
- `^:php/readonly` on struct name → `readonly` typed properties (untagged fields default `readonly mixed`) + a constructor-rebuilding `put()` override so persistent updates work. Stays a plain `final class` (cannot be a PHP `readonly class` because `AbstractPersistentStruct` is not readonly).
- `:php` marker opens a block of bare PHP magic methods (`__invoke`/`__toString`/`__get`) emitted on the class with no interface (`PhpBlockAnalyzer`, carried as a `DefStructInterface` with empty name that `DefStructEmitter` drops from `implements`). Custom `__invoke` must be 1-arg or variadic (`PhpBlockAnalyzer` rejects bad arity).

### definterface (`DefInterfaceEmitter`)

- Method arg `^{:tag <type>}` → typed param; method name `:tag` → return type.
- `^{:php/attr [...]}` on interface name, method, or method **parameter** → interface-/method-/parameter-level attributes (parameter form inlined: `show(#[\Autowire] string $repo)`).
- Trailing `:php/const` block → typed class constants: `:php/const (^{:tag int} MAX 100)` → `const int MAX = 100;` (value must be int/float/string/bool/nil literal; `DefInterfaceSymbol`/`PhpClassConst`/`DefInterfaceEmitter`).
- The `definterface` macro only wraps method forms in Phel fns; the const block passes through to `definterface*` only.

### defenum (`defenum*`, `DefEnumSymbol`/`DefEnumNode`/`DefEnumEmitter`)

- Emits a native PHP `enum`; cases are keyword-named with an optional `int`/`string` value (all-or-none → backed vs pure enum). Guarded by `enum_exists`.
- `^{:php/attr [...]}` on enum name → class-level attributes.
- Optional implementations tail after cases (interface symbols + methods, plus a `:php` block) parsed via shared `InterfaceImplementationsAnalyzer`, emitted as `implements` + methods via `MethodEmitter`.

### Shared across constructs

- `InterfaceImplementationsAnalyzer` (used by `defstruct` and `defenum`) parses interface symbols (reflection-validated) and `:php` blocks (become a `DefStructInterface` with empty name).
- `PhpBlockAnalyzer::analyze` takes an `enforceInvokeArity` flag (true only for structs, whose map `__invoke` constrains arity).
- `^{:php/doc <str|[str...]>}` on any name/field/method → PHPDoc block (one-line string or multi-line list/vector) above the construct, so phpstan/psalm see generated classes as typed.
- `^:php/override` on a method (defstruct/defenum interface impls, definterface methods) → `#[\Override]` (PHP 8.3); `PhpAttributeEmitterTrait::phpAttributeLines` renders it ahead of explicit `:php/attr` lines. Struct/enum inline method impls emit method-level `:php/attr`/`:php/doc`/`^:php/override` too.
- Export wrappers carry the same `:php/attr` via `Interop`'s `CompiledPhpMethodBuilder` (see `src/php/Interop/CLAUDE.md`).

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`.

- `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) both read/write the same slot.
- `GlobalEnvironmentSingleton` is retained as ABI shim; emitter writes literal `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` calls into generated PHP (baked into cached `.phel` files — see rename constraint above).

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `src/php/Shared/CLAUDE.md`). Two encoders at different boundaries:

- `encodePhpNs` — backslash form, for PHP `namespace` declarations and class FQNs.
- `encodeRegistryKey` — dot form, for Phel registry lookups.

Analyzer uses dot-separated namespace internally; emission routes through `encodePhpNs` for PHP output.
