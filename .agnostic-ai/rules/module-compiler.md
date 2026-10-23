---
name: module-compiler
description: 'Compiler module: lexer, parser, reader, analyzer, simplifier and emitter pipeline.'
scope: src/php/Compiler
---

# Compiler Module

Core compilation pipeline: Phel source → tokens → AST → analyzed nodes → PHP code.

## Public API (Facade)

| Category | Methods |
|----------|---------|
| Compilation | `compile`, `compileForCache`, `compileForm` (return `EmitterResult`) |
| Evaluation | `eval(string, CompileOptions)`, `evalForm(mixed, CompileOptions)` |
| Pipeline | `lexString → TokenStream`, `parseNext → ?NodeInterface`, `parseAll → FileNode`, `read → ReaderResult`, `analyze(mixed, NodeEnvironmentInterface) → AbstractNode` |
| Tooling | `readFormsBestEffort(code, source) → Generator` of top-level forms for tooling over a buffer mid-edit (`Application/BestEffortFormReader`). Never throws: parse failure ends the stream, read failure skips the form. `getReturn()` is `true` when something was dropped (#3292) |
| Macros | `macroexpand1`, `macroexpand` |
| Analyzer environment | `emptyNodeEnvironment`, `enableDeprecationWarnings`, `deprecationWarningsEnabled` (config key, env var or flag; reported by `phel config`), `withoutDeprecations` (read a source without reporting its notices) |
| Suggestions | `findSimilarNames(typed, candidates)`: "did you mean" ranking (`SymbolSuggestionProvider`), used by Build and Lint's `phel/unresolved-namespace` |
| Environment | `initializeGlobalEnvironment`, `resetGlobalEnvironment`, `isGlobalEnvironmentInitialized`, `getGlobalEnvironment`, `initializeNewGlobalEnvironment`, `setGlobalEnvironment` |
| Namespace state | `getNamespaceEnvironmentData`, `restoreNamespaceEnvironmentData` |
| Debugging | `enableDebugLineTap`, `disableDebugLineTap` |
| Utility | `encodeNs` (via `Munge::encodePhpNs`), `hasBalancedParentheses` |

## Dependencies

- **Filesystem**: `FilesystemFacadeInterface::class`, the module's only Provider entry.
- **Config**: `PhelConfig`, wrapped by `CompilerConfig` (`assertsEnabled()`, `warnDeprecationsEnabled()`, `isIntermediateCacheEnabled()`, `getCacheDir()`).
- **Lang**: the widest edge (~200 files). Every phase after the lexer uses `Phel\Lang` values; the emitter writes their FQNs into generated PHP.
- **Shared**: `Munge`, `Printer`, exceptions, `SourceMap\VLQ`. Shared points back through `Facade/CompilerFacadeInterface`; see "Compiler Back-Edge" in `.agnostic-ai/rules/module-shared.md`.

The source map is split on purpose: the writer (`Domain/Emitter/OutputEmitter/SourceMap/SourceMapGenerator`, `SourceMapState`) is emitter state; the reader (`Shared\SourceMap\SourceMapConsumer`) lives in Shared because Command decodes maps and must not `new` a compiler-internal class.

## Phase Pipeline

Lexer (→ `TokenStream`) → Parser (→ `FileNode`) → Reader (→ `ReaderResult`) → Analyzer (→ `AbstractNode` with `NodeEnvironment`) → Simplifier → Emitter (→ `EmitterResult`).

- `EmitterResult` and `ReaderResult` are public value objects in `Phel\Shared`.
- Lexer `Token` and parse-tree nodes live in `Phel\Shared\Parser\Node`; `ExpressionParserFactory` produces them (sub-parsers in `Domain/Parser/ExpressionParser/`).
- The `#php` reader tag expands vectors to indexed PHP arrays and maps to associative ones; literal keyword map keys become strings at read time.

### Unterminated-form reporting

`Application/Parser` stacks the delimiter of every open form. A closer or EOF reaches `readExpression` only when the innermost open form did not consume it; `Domain/Parser/OpenForm` turns the stacked opener into the message, the `ErrorCode` (`UNTERMINATED_LIST` / `_VECTOR` / `_MAP` / `_TABLE` for `#{}`) and the caret location: the opening delimiter, never where the stream ran out. `ListParser` keeps its own throw for a stream ending without `T_EOF`.

An unclosed `"` never fails to lex: the atom rule swallows it (`Balance` relies on that, see `.agnostic-ai/rules/module-balance.md`). `Parser::parseAtomNode` raises `UNTERMINATED_STRING` on the leading quote.

### Interop shorthand expansion

Clojure-style interop spellings expand to `php/*` forms before analysis, never registered as special forms (`LanguageSurfaceSpecTest` fails on a spec row with no dispatch entry):

- Call position (`AnalyzePersistentList`): `(.m obj …)`, `(.-field obj)`, `(\C/m …)`, `(\C. …)`.
- Value position: `Domain/Analyzer/TypeAnalyzer/QualifiedMemberExpander`, from `AnalyzeSymbol` when global resolution finds nothing: `\C/CONST` → `php/::`, `\C/$prop` → `php/::` (static property; the sigil decides, no reflection), `\C/m` → `php/callable`, `\C/.m` → an `fn` of the receiver.

`QualifiedMemberExpander` reflects the class to tell a static method from a constant. A class with both under one name resolves to the **constant** (why `\C/new` is not a constructor); an unresolvable or unloadable class falls back to the constant reading.

`Domain/Analyzer/QualifiedMemberSyntax` is the single statement of how a class member is spelled. Call position, value position and `phel.core/set!` all use it, so `(\Foo/m 1)`, `\Foo/CONST` and `(set! \Foo/slot v)` cannot disagree; `set!` re-spells it in Phel because the stdlib cannot import `@internal` classes. Purely lexical: `PhpClassLike` answers existence.

`NodeEmitter/PhpObjectSetEmitter` shares `PhpObjectCallDispatchResolver` with the read path, so a class-name target is static however written, and prefixes a bare static member with `$`: a class constant is not assignable (#2907).

Bare host symbols read by **position**, never by probing the class table (ADR 0016). `SymbolResolver::resolveBareHostSymbol()` reads an all-caps name as a global constant; `Domain/Analyzer/BareHostClass` re-reads it as a class wherever only a class can stand (member target in `PhpObjectCallSymbol`, `PhpNewSymbol`, `PhpCallableSymbol`), so `(WP_CLI/log "x")` emits `\WP_CLI::log("x")` whether or not the class loads. `php/NAME` stays a constant everywhere. Do not reintroduce probing: emitted PHP then depends on what the process had autoloaded, and the compiled-code cache freezes whichever ran first.

`Domain/Analyzer/PhpClassLike` is the single class-like existence predicate. It still answers for `:use` alias targets (asserted at compile time), lowercase-initial bare names (`stdClass`), and `Environment/AmbiguousBareHostWarner`, which warns when value position reads a constant that a loadable class shadows.

`Domain/Analyzer/UnresolvedSymbolAdvisor` builds the unresolved-symbol error. A qualified symbol is searched only in its own namespace (alias resolved, plus `BundledSymbolIndex`); an unknown alias names the bundled namespace to require; a bare name from a bundled namespace names the `:require ... :refer`. `BundledSymbolIndex` regex-reads public defs from stdlib sources and never loads a namespace, keeping the error path cheap (#3458).

### Simplification pass

Runs after `ConstantFolder` (in `Domain/Analyzer/TypeAnalyzer/Simplification/`):

- Drops pure non-tail expressions from `(do ...)` via `PureExpressionDetector`.
- Inlines calls at opt level >= 2 via `CallInliner` (purity from `ConstantFolder` for known calls, `SymbolicPurityDetector` for structural checks).
- `^:pure` opts a `defn` into inlining trust (author owns correctness).

**`update` with a literal fn** (`UpdateLiteralFnLowering`, #3322): `InvokeSymbol` lowers an analysed `(update m k (fn [v x ...] body) x ...)` with a literal single-arity `FnNode` into the `let` the runtime `update` runs.

- Gates: opt level >= 2; never on a `:redef` or `:dynamic` `update` (same as `CallInliner`).
- Bindings land in the caller's PHP frame, so only return position outside a `try` (`NodeEnvironment::isWithinTry()`, reset by a fn body). Other positions keep the closure.
- Works on the analysed call: the body is analysed once in its own env (macros see the real `&env`, params untyped); declining builds the ordinary `CallNode`.
- Params become `let` bindings under fresh PHP names (`ParamRenamer`); a nested fn declines, since its captures name the param. `ExpressionContextRebuilder` moves the body's value to expression position.
- The `let` is marked `LetNode::withSplicedFnBodyAfter()`: `ParamTypeInferrer` stops there as at a fn body, `TailCallRewriter` carries the mark, `CallInliner` and `LetSimplifier` leave it alone.
- Body and extra arguments must be fully on the `SpliceableBody` allowlist: literals, local and global reads, collection literals without reader metadata, `php/aget`, `if`/`do`/`let` (not `loop`), nested fns, and calls to PHP operators except `=`/`=&`, a few pure builtins (`max`, `min`, `abs`, ...), keywords, or global Phel fns that are not `^:dynamic`/`^:redef` and whose `__invoke` has no by-ref param (reflected; undefined counts as by-ref). Extra arguments are restricted because the closure captured enclosing locals before they ran. Keep it an allowlist: a denylist kept leaking.
- `recur` to the fn, rest/multi-arity/named fns, tagged params and any return type (declared or inferred) keep the closure.

### Cache-mode definition registration

A cache-mode file also registers its definitions in the global environment, so a later from-source compile resolves them (#3470).

- A top-level `def` of a fn or constant goes into an `addCompiledDefinitions([...])` batch (`OutputEmitter/DeferredDefinitionRegistrations`, fed by `FileEmitter`), emitted before any other top-level form (which may `(load ...)` another file or throw); the rest of the file follows.
- Any other `def` keeps the inline `hasDefinition` guard: it registers only if it runs.
- Never prepend: a leading line shifts every source map mapping, and `namespace` must come first.
- The batch marks only absent names and never raises duplicate-definition, so reloading a cached file is harmless.

### Reader-result cache

Opt-in, off by default (`CompilerConfig::isIntermediateCacheEnabled()`). Wired only into `createCodeCompilerForCache` (build path), never the REPL.

- `CodeCompiler` persists read results via `Domain/Cache/ReaderResultCacheInterface`: `Infrastructure/Cache/FileSystemReaderResultCache` (gzip'd `serialize` under `<cacheDir>/read-result/`, key `md5(version|optLevel|source)`) or `NullReaderResultCache`.
- A warm hit skips lex/parse/read and replays each form's read-phase gensym delta (`Domain/Cache/CachedReaderResult`) before analysis, so `Symbol::gen()` follows the cold trajectory.
- GOTCHA: replayed forms are deserialized, so map keys must compare by value (`Keyword::equals`/`Symbol::equals`), not identity, or keyword-keyed lookups silently miss.
- Gensym names are process-global; a build mixing fresh compiles with compiled-code-cache hits can renumber them (independent of this cache).

### Call-site arity shortcut

In build mode a call to a multi-arity global at a fixed-arity count emits `($__phel_call_N ??= \Phel::fnSlot(ns, name))->invokeArityN(args)` (#3354). `fnSlot` wraps a non-`AbstractFn` in `Lang\ForeignFn` and leaves a missing one `null`, so callee and arguments are written once. Do not reintroduce an `instanceof` guard with arguments in both arms: nested calls double the code per level. For a variadic multi-arity callee (`max-arity` `nil`), `FixedAritySlots` checks the count hits a fixed arity on the definition the process holds; it only chooses between two correct calls.

### Multi-arity fn classes

`MultiFnAsClassEmitter` writes one method per arity (#3355).

- An arity that can override `AbstractFn::invokeArityN` (`MethodEmitter::hasArityMethodCompatibleSignature()`: no param type, no by-ref param, return type not `void`) is emitted as it. A typed or by-ref fixed arity gets `phelArityN` plus an `invokeArityN` forwarder; the variadic arity is `phelArityVariadic`.
- `__invoke` takes one optional param per position of the widest fixed arity (`$a0 = null, ...`) and dispatches with `match` on `func_num_args()`; the variadic arm reads `func_get_args()`. Not `...$args`: it packed every call into an array (#3469). Never test params for `null` to find the arity: `nil` is a real argument. The export wrapper forwards `...$args` (`Interop` rule).
- Captures are constructor properties read at the top of each method. Do not store arity bodies as closures in properties: each bound `$this`, so every runtime fn value (`comp`, `partial`) allocated a closure per arity and a reference cycle only the GC frees.

## Key Constraints

- Never bypass a phase; each consumes only the previous phase's output.
- Analyzer nodes carry `NodeEnvironment` with correct context.
- Emitter handles every node type; missing cases throw, never skip.
- Special forms are registered centrally; no ad-hoc handling in the analyzer loop.
- Source locations propagate through all phases.
- Every special-form analyzer checks argument count before reading an argument (`AssertsFormArityTrait::assertArityAtLeast()`). Otherwise `PersistentListInterface::get()` throws a runtime `[PHEL403] Index out of bounds` with no snippet. `SpecialFormArityTest` walks the `AnalyzePersistentList` registry and fails on anything but an `AnalyzerException` (#3297).
- Type declarations reject `let` and `is` case-insensitively; interface constants also reject `namespace` (PHP 8.6, shared via `ReservedDeclarationName`).
- A reference assignment in return position goes through a temporary: returning it directly triggers `Invalid opcode 62/4/0` in PHP 8.6 RC2's OPcache. Both operands still evaluate once.
- Do NOT rename `GlobalEnvironmentSingleton`: the emitter writes `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` into generated PHP, baked into cached `.phel` files. It is an ABI shim over the same slot as `GlobalEnvironmentManager`.
- Do NOT rename `Phel\Lang\LoadClasspath`: `LoadEmitter` bakes its FQN into generated PHP. It lives in `Lang` because its state is the `*load-classpath*` slot in `Lang\Registry`.

## Type-Specialized Emission

`ParamTypeInferrer` and `ReturnTypeInferrer` graft `:tag` meta onto binding symbols. Contract: propagate only analyzer-published types, never fabricate.

`TagCanonicalizer` rewrites a tag naming a `:use` import to the rooted class where it is declared: `FnSymbol` (params + arg-vector return tag), `AnalyzeBindingsTrait` (`let`/`loop`), `DefSymbol`, `ForeachSymbol`, `DefStructSymbol`, `DefInterfaceSymbol`. Union (list) and intersection (vector) tags are rewritten per member, since `PhpAttributeEmitterTrait` joins them verbatim. Downstream readers (inferrers, emitters, another namespace's caller, an inlined body) resolve through stateless `Shared\TagResolver`, never the active `:use` table. A new form binding a tagged symbol must canonicalize too, or imported tags emit unqualified.

Inferred tags replace AST symbols with metadata-bearing copies. `NodeEnvironment` clones share an object-keyed inferred-type table, so a late result (loop fixpoint, inferred defn param) reaches nodes analyzed earlier. Do not restore in-place `Symbol::withMeta()` mutation.

Two halves per family:

- **Eligibility**: `Domain/Emitter/OutputEmitter/`: `NumericOperationSpecialization`, `TypePredicateSpecialization`, `TypedValueSpecialization`, `TypedCollectionMethodSpecialization`, `AssocConjSpecialization`, `GetInSpecialization`, `AssocInSpecialization`, `AtomMethodSpecialization`, `NilAndBooleanCheckSpecialization`, `ReduceSpecialization`, `ConstructorSpecialization`; aggregated by `CallSpecialization`.
- **Emission**: one `Specialized/*CallEmitter implements SpecializedCallEmitterInterface` per family under `NodeEmitter/Specialized/`. `CallEmitter` builds them once and loops `tryEmit()` before the generic path.

Predicates are disjoint, so order does not matter. To add a family: a `*Specialization` class, registered in `CallSpecialization::isSpecialized()`, plus a `Specialized/*CallEmitter` in `CallEmitter`'s list.

**Constructors.** `ConstructorSpecialization` is gated on the **callee alone** (no tag, no arity): `(list …)`, `(vector …)`, `(queue …)`, `(hash-map …)`, `(array-map …)` become `\Phel::list([…])` and friends. Odd counts are lowered on purpose: `\Phel::map([1])` raises the same error as the runtime fn. `apply`/higher-order uses keep the runtime fn; a local shadow is a `LocalVarNode`, which `PhelCoreCall::nameOf()` declines.

**Multi-key `assoc`.** On a typed map or vector, `AssocConjSpecialization::typedAssocPairs()` emits a `->put()` / `->update()` chain. Otherwise `CallEmitter` itself emits nested three-argument `phel.core/assoc` steps, outside `isSpecialized()`, so the call keeps its `$__phel_call_N` slot. Arguments evaluate once, left to right, each step before the next pair (#3317). `(assoc! t k1 v1 …)` takes the untyped steps (#3318) but never the typed chain: `assoc!` on a persistent target must throw.

**`get-in`.** `GetInSpecialization` lowers a literal path whatever the target. A tagged map/vector with two arguments unrolls to `($m[k] ?? null)` subscripts; otherwise `\Phel\Lang\GetIn::path($m, [k1, k2], nf)`, with an all-literal key array hoisted to a `$__phel_const_N` slot from `ConstantScope::reserveAsPhpArray()`. Never share that slot with an equal vector literal, which holds a `PersistentVector`. Each argument is written once, so nesting stays linear (#3320). A path with metadata keeps the runtime call.

**`assoc-in` / `update-in`.** `AssocInSpecialization` lowers a literal path whatever the target to `\Phel\Lang\AssocIn::path($m, [k1, k2], $v)` or `AssocIn::update($m, [k1, k2], $f, ...)`, sharing `get-in`'s `reserveAsPhpArray()` slot. No typed variant: `AssocIn` handles maps and vectors inline and defers other levels to `phel.core/get` / `phel.core/assoc`, so transients, lists and bad indexes behave as at runtime (#3328). A variable or metadata-carrying path, `apply`, and a local shadow keep the runtime call.

**Guarded core calls** (#3351). Two-argument `<` `<=` `>` `>=` `=` `not=` `+` `-` `*` and one-argument `zero?` `pos?` `neg?` `inc` `dec` that no typed family took are lowered by `Specialized/GuardedCoreCallEmitter` (eligibility in `GuardedCoreCallSpecialization`) to native PHP behind an `is_*` guard, falling back to the runtime call: `(\is_scalar($a) && \is_scalar($b) ? ($a < $b) : <call>)`, `($a === 1 || (!(\is_int($a)) && <call>))`, `(\is_int($a) && \is_int($b) && \is_int($t = $a + $b) ? $t : <call>)`.

- Arithmetic keeps the native result only while it is an int: PHP yields a float exactly on overflow, which the runtime promotes to `BigInt` (#3468).
- Each guard is the exact condition under which the runtime fn ends in the native operator (see the class docblock).
- Emitted by `CallEmitter` outside `isSpecialized()`, so the call keeps its slot; the fallback takes the arity shortcut. Bool-returning for `BooleanExprDetector`.
- Locals and literals are read in place; other operands spill into a `$__phel_guard_N` temporary on first read, placed where PHP always evaluates it (`===` operands, `&` not `&&` between guards), so each runs once, in order.
- Quoted forms and collection literals decline.

**Sequential destructuring** (#3356). `VectorBindingDeconstructor` gives `[a b & r]` an indexed path: one `iv (php/instanceof v PersistentVectorInterface)` binding, then each position reads `(php/aget v i)` under `(php/=== iv true)`, else the exact `first`/`next` walk (trailing `next` included), so lazy seqs realize the same cells and scalars throw where they did.

- A vector's `& r` is `(.cdr v)` after one position, `Destructure::nthNext(v, i)` after more.
- `(php/=== iv true)`, not bare `iv`, skips the truthiness adapter, which costs the list walk more than the fast path saves.
- Added bindings except `v` carry `ReturnTypeInferrer::SYNTHETIC_BINDING`, so a pattern does not make that inferrer publish a return type (`(fn [[x y]] 1)` stays unannotated).
- `[]` and `[& r]` get no test.

GOTCHA: only eager core fns lower to a native loop. `reduce` (3-arity) qualifies; `map`/`filter` do NOT: they return a `LazySeq` over a `Seq::map`/`Seq::filter` generator and `copy-meta` the source, so a `foreach` would change the return type, break infinite seqs and shift side effects. Gain is small anyway: `f` reaches the generator once.

## Generated-Class Attributes & Typed Signatures

`DefStructEmitter`, `DefInterfaceEmitter` and `DefEnumEmitter` read per-symbol metadata, sharing `PhpAttributeEmitterTrait` (tag + `:php/attr`) and the pure `Phel\Shared\PhpAttributeRenderer`. All opt-in; untagged forms emit unchanged.

`:tag` values: bare symbol/string verbatim (`?int`, `self`, `DateTime`); list = union (`a|b`); vector = intersection (`a&b`).

### defstruct (`DefStructEmitter`)

- Field `^{:tag <type>}` → typed property (`protected int $id;`).
- `^{:php/attr [...]}` on struct name or field → class- or property-level PHP 8 attributes.
- `^{:php/json true}` on name → `\JsonSerializable` (`jsonSerialize()` returns the field map).
- `^{:php/stringable true}` on name → `\Stringable`.
- `^:php/readonly` on name → `readonly` typed properties (untagged default `readonly mixed`) + a constructor-rebuilding `put()` override. Stays a plain `final class`: `AbstractPersistentStruct` is not readonly.
- A `:php` marker opens a block of bare magic methods (`__invoke`/`__toString`/`__get`), carried by `PhpBlockAnalyzer` as a `DefStructInterface` with empty name that `DefStructEmitter` drops from `implements`. Custom `__invoke` must be 1-arg or variadic.

### definterface (`DefInterfaceEmitter`)

- Method arg `^{:tag <type>}` → typed param; method name `:tag` → return type.
- `^{:php/attr [...]}` on interface name, method, or **parameter** (inlined: `show(#[\Autowire] string $repo)`).
- Trailing `:php/const` block → typed constants: `:php/const (^{:tag int} MAX 100)` → `const int MAX = 100;` (int/float/string/bool/nil literal only; `DefInterfaceSymbol`/`PhpClassConst`/`DefInterfaceEmitter`).
- The `definterface` macro wraps only method forms; the const block passes through to `definterface*`.

### defenum (`defenum*`, `DefEnumSymbol`/`DefEnumNode`/`DefEnumEmitter`)

- Native PHP `enum` guarded by `enum_exists`; keyword-named cases with optional `int`/`string` values (all-or-none: backed vs pure).
- `^{:php/attr [...]}` on name → class-level attributes.
- Optional tail after cases (interfaces + methods, `:php` block) parsed by `InterfaceImplementationsAnalyzer`, emitted via `MethodEmitter`.

### Shared across constructs

- `InterfaceImplementationsAnalyzer` (`defstruct`, `defenum`) parses reflection-validated interface symbols and `:php` blocks.
- `PhpBlockAnalyzer::analyze` takes `enforceInvokeArity` (true only for structs, whose map `__invoke` constrains arity).
- `^{:php/doc <str|[str...]>}` on any name/field/method → PHPDoc block, so phpstan/psalm see typed generated classes.
- `^:php/override` on a method → `#[\Override]` (PHP 8.3), rendered by `PhpAttributeEmitterTrait::phpAttributeLines` ahead of `:php/attr` lines. Struct/enum inline methods emit `:php/attr`/`:php/doc`/`^:php/override` too.
- Export wrappers carry the same `:php/attr` via `Interop`'s `CompiledPhpMethodBuilder` (see `.agnostic-ai/rules/module-interop.md`).

## Compiler Diagnostics

Two channels, differing only in the gate. Both raise through `Domain/Diagnostic/ErrorNotice::raise($message, $level)`, which writes one stderr line prefixed `deprecated: ` or `warning: ` (ADR 0017).

| Channel | Level | Gate |
|---|---|---|
| `Domain/Deprecation/DeprecationWarnings` | `E_USER_DEPRECATED` | off unless `--warn-deprecations` |
| `Domain/Diagnostic/CompilerWarnings` | `E_USER_WARNING` | always on |

`CompilerWarnings` is for diagnostics that already changed program behaviour, so silence is the bug. It owns bundled-stdlib suppression (via `DeprecationWarnings::isBundledStdlibSource()`) and per-`(file, subject)` dedup; `reset()` is for test `tearDown()`. Both detectors are called from `DefSymbol` before `addDefinition`, so they warn once at `def` time like Clojure, not per call site:

| Detector | Catches |
|---|---|
| `Domain/Analyzer/Environment/ReferShadowWarner` | a `def` whose name is already `:refer`red into the namespace (#2897). Anchors on the *name* symbol: the enclosing list carries a `defn` expansion origin in `src/phel/core/defs.phel`, which stdlib suppression would silence |
| `Domain/Analyzer/Environment/ClassShadowWarner` | a `def` whose name is a loadable PHP class (#2876). Filters on a PHP-identifier regex first, because `PhpClassLike::exists()` autoloads and the stdlib runs `def` thousands of times. Warns, not refuses: refusing is breaking and belongs to the major that drops the leading `\` |

Unrelated to `Phel\Lsp\Application\Diagnostics` and `Phel\Shared\Api\Diagnostic`.

## Deprecation Warnings

`Domain/Deprecation/DeprecationWarnings` is the single process-wide switch for every compiler `E_USER_DEPRECATED`. Enabled by `--warn-deprecations` (`Console\Application\WarnDeprecationsFlag`), `PHEL_WARN_DEPRECATIONS`, or the `warn-deprecations` config key (`CompilerFactory::createAnalyzer()`). It alone owns the flag, stdlib suppression, dedup, macro-expansion attribution, syntax message shape and per-compile recording. Detectors only detect: no flag, dedup table or emitter.

| Detector | Catches |
|---|---|
| `Application/Lexer` | bare `#` comments, `#\| ... \|#` blocks, `\|()` short fns, `,`/`,@` unquote |
| `Domain/Reader/QuasiquoteTransformer` | `$` auto-gensym |
| `Domain/Analyzer/Environment/BackslashSeparatorDeprecator` | `\` namespace separator |
| `Domain/Analyzer/Environment/DeprecatedDefinitionWarner` | a resolved definition with `:deprecated` meta (`:superseded-by` names the replacement), project code too |
| `.../SpecialForm/Binding/Deconstructor/MapBindingDeconstructor` | key-first map-destructuring pairs (`{:key local}`) and ambiguous both-sides-bind pairs (`{k v}`), which stay key-first until the next major (#3115) |

Recording and gating:

- `startRecording()`/`stopRecording()` wrap `CodeCompiler::compileString()`; `EmitterResult::getDeprecations()` is stored by the compiled-code cache and raised again by `replayDeprecations()` on a hit (#3222). `replay()` skips messages this process already raised (a cold run compiles a dependency, then loads the entry it wrote).
- Detectors gate on `isDetecting()`, not `isEnabled()`: notices are *found* when the flag is on or a compile is recording, *raised* only when the flag is on, so a plain run still fills the cache for a later `--warn-deprecations` run.
- Dedup suppresses the *raise*, never the *recording*: each recording frame has its own dedup table, so a warm replay still reports (#3222).
- `withoutNotices()` (facade `withoutDeprecations()`) mutes everything for a non-compile read. Build's namespace scan needs it, or scanned files leak notices into another compile's cache entry (#3381).
- `raise()` prints paths under the working directory as relative (`Domain/Diagnostic/WorkingDirectoryPaths`) at output time; records and dedup keys stay absolute.

Raising:

- Never suppress a notice with `@`: it hides it even under `--warn-deprecations`. Call `warn()` / `warnForSource()` / `warnOnceForSource()` / `warnSyntax()`.
- `ErrorNotice::raise()` writes to stderr itself, because PHP's renderer printed each notice twice and named `ErrorNotice.php` instead of the user's source (#3262). Only with a userland error handler installed does it call `trigger_error()`, pinning `display_errors` to `stderr` for the call (skipped when display is off or already on stderr, so it never *enables* a silenced notice). Keep new notices on `raise()`: a notice raised inside the emitter's `ob_start()` was once spliced into generated PHP and broke the compile (#2827).
- `syntaxMessage()` is the only way to phrase a syntax notice and has no room for a removal version, on purpose: named versions go stale (#2783). Guarded by `DeprecationWarningsTest::test_syntax_message_has_one_shape_and_no_room_for_a_version`.
- `warnOnceForSource()` dedups per `(file, subject)` on the `realpath`-normalized file (a file reaches the analyzer under two spellings, #3262), for subjects that recur (a deprecated definition, a `\` symbol). Syntax notices do not dedup: each occurrence is a separate edit.
- `isEnabledForSource()` / `isBundledStdlibSource()` drop notices from `src/phel` or with no file. The Lexer resolves it once per source. Paths are `realpath`-normalized (memoized, only once warnings are on) so relative stdlib paths still match.

Attribution:

- `InvokeSymbol::enrichLocation()` stamps the call site onto location-less forms from a macro/inline expansion and records the definition's location as `SourceLocation::getExpansionOrigin()`. Macro arguments keep reader positions. Detectors must never report against the call site: the `\` in `(delay ...)`'s expansion lives in `src/phel/core/lazy.phel` (#2827).
- `warnOnceAtOrigin()` is the one place this rule lives (`warnSyntax()` applies it too); do not re-derive it per detector. Given a location and a `fn (string $file, int $line): string` builder, it reports an unexpanded form where it sits, an expansion against the macro's file with ` (reached by expanding a macro at <call site>)`, suppresses a stdlib origin, and stays silent on `SourceLocation::unknown()`.
- `SymbolResolver::globalVarNode()` is the single `GlobalVarNode` construction point, so each resolved definition passes the `:deprecated` check once. `GlobalEnvironment` injects both analyzer detectors into it.

`Domain/Deprecation/SupersededFormRejector` rejects `php/new`, `php/->`, `php/::` and `set-var` outright (ADR 0018, #2877, #2888). `CodeCompiler` (cold path only) and `EvalCompiler` run it on each read form; Api's `ReadAndAnalyzeStage` (lint, analyze, LSP, api-daemon) uses `CompilerFacade::rejectSupersededForms()` (#3456). The reader is the seam because macro output never passes through it. A quasiquote is already lowered there (`(apply list (concat (list (quote php/new)) …))`), so the name is quoted data, not a head; plain `quote` is skipped.

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`; `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) share its slot.

### Duplicate-definition reporting

`definitionLocations` records first definitions, so `DuplicateDefinitionException` carries the redefinition's location, `ErrorCode::DUPLICATE_DEFINITION` and a `first defined at …` note (#3267). It gets a snippet because `CodeCompiler`/`EvalCompiler` wrap every `AbstractLocatedException` from analysis (`AnalyzerException` is `final`).

In build mode a redefinition counts only within one `compileString` (`GlobalEnvironment::enterCompileUnit()`), because builds recompile already-loaded namespaces. A name the registry already held is not claimed, so `declare` stays redefinable (#3649).

`phpClasses` maps, per namespace, each lowercased munged PHP class a `defstruct*`, `defexception*`, `defenum*` or `definterface*` emits to the claiming name; a second name raises `DuplicateDefinitionException::forPhpClass` (#3524).

- Interactive mode keeps the check (the same name may reclaim). Analysis mode (lint, `phel analyze`, LSP) skips it: a re-read buffer is not a redefinition.
- Every `ns` form clears its namespace; `snapshot()`/`restore()` carry it so a failed nREPL eval rolls back its claim.

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `.agnostic-ai/rules/module-shared.md`):

- `encodePhpNs`: backslash form, for PHP `namespace` declarations and class FQNs.
- `encodeRegistryKey`: dot form, for Phel registry lookups.

The analyzer uses dot form internally; emission goes through `encodePhpNs`.
