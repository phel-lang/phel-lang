---
name: module-compiler-emitter
description: 'Compiler emitter: registration, arity dispatch, specialization and generated classes.'
scope: src/php/Compiler/Domain/Emitter
---

# Compiler Emitter

Part of the Compiler module; read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints.

## Cache-mode definition registration

A cache-mode file also registers its definitions in the global environment, so a later from-source compile resolves them (#3470).

- A top-level `def` of a fn or constant goes into an `addCompiledDefinitions([...])` batch (`OutputEmitter/DeferredDefinitionRegistrations`, fed by `FileEmitter`), emitted before any other top-level form (which may `(load ...)` another file or throw); the rest of the file follows.
- Any other `def` keeps the inline `hasDefinition` guard: it registers only if it runs.
- Never prepend: a leading line shifts every source map mapping, and `namespace` must come first.
- The batch marks only absent names and never raises duplicate-definition, so reloading a cached file is harmless.

## Call-site arity shortcut

In build mode a call to a multi-arity global at a fixed-arity count emits `($__phel_call_N ??= \Phel::fnSlot(ns, name))->invokeArityN(args)` (#3354). `fnSlot` wraps a non-`AbstractFn` in `Lang\ForeignFn` and leaves a missing one `null`, so callee and arguments are written once. Do not reintroduce an `instanceof` guard with arguments in both arms: nested calls double the code per level. For a variadic multi-arity callee (`max-arity` `nil`), `FixedAritySlots` checks the count hits a fixed arity on the definition the process holds; it only chooses between two correct calls.

## Multi-arity fn classes

`MultiFnAsClassEmitter` writes one method per arity (#3355).

- An arity that can override `AbstractFn::invokeArityN` (`MethodEmitter::hasArityMethodCompatibleSignature()`: no param type, no by-ref param, return type not `void`) is emitted as it. A typed or by-ref fixed arity gets `phelArityN` plus an `invokeArityN` forwarder; the variadic arity is `phelArityVariadic`.
- `__invoke` takes one optional param per position of the widest fixed arity (`$a0 = null, ...`) and dispatches with `match` on `func_num_args()`; the variadic arm reads `func_get_args()`. Not `...$args`: it packed every call into an array (#3469). Never test params for `null` to find the arity: `nil` is a real argument. The export wrapper forwards `...$args` (`Interop` rule).
- Captures are constructor properties read at the top of each method. Do not store arity bodies as closures in properties: each bound `$this`, so every runtime fn value (`comp`, `partial`) allocated a closure per arity and a reference cycle only the GC frees.

## Type-Specialized Emission

Tags come from the analyzer (`.agnostic-ai/rules/module-compiler-analyzer.md`, "Type Tags").

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
