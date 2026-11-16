---
name: module-compiler-emitter
description: 'Load when changing the compiler emitter: definition registration, arity dispatch, call specialization or generated classes.'
scope: src/php/Compiler/Domain/Emitter
---

# Compiler Emitter

Read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints.

## Source-path provenance

`SourcePathEmitter` writes the original magic-constant value in STATEMENT mode. FILE/CACHE output tags it with `Shared\SourceMap\SourcePathMarker`; `LoadEmitter` tags its source-directory fallback the same way. The tags stay ordinary values in compile-time evaluation and runtime caches; Build consumes them only when writing portable artifacts. Mode checks must record divergence so `CodeCompiler` cannot evaluate a relocated fragment as a statement.

## Cache-mode definition registration

A cache-mode file also registers its definitions in the global environment.

- A top-level `def` of a fn or constant joins an `addCompiledDefinitions([...])` batch (`OutputEmitter/DeferredDefinitionRegistrations`, fed by `FileEmitter`), emitted before any other top-level form, since one may `(load ...)` or throw. Any other `def` keeps the inline `hasDefinition` guard.
- Never prepend a line: it shifts source map mappings, and `namespace` must come first.
- The batch marks only absent names and never raises duplicate-definition, so reloading a cached file is safe.

## Call-site arity shortcut

In build mode a call to a multi-arity global at a fixed arity emits `($__phel_call_N ??= \Phel::fnSlot(ns, name))->invokeArityN(args)`. `fnSlot` wraps a non-`AbstractFn` in `Lang\ForeignFn` and leaves a missing one `null`, so callee and arguments are written once. Never add an `instanceof` guard with arguments in both arms: nested calls double the code per level. For a variadic callee, `FixedAritySlots` checks that the count hits a fixed arity.

Core fn values also get build-mode slots holding the registry value, not a `fnSlot` wrapper. Reference reads and `^:dynamic`/`^:redef` vars stay uncached. `BodyConstantScanner` skips global call heads when scanning values, so a consumed callee leaves no orphan value slot (`OrphanCallSlotAuditTest`).

## Multi-arity fn classes

`MultiFnAsClassEmitter` writes one method per arity.

- An arity whose signature can override `AbstractFn::invokeArityN` (`MethodEmitter::hasArityMethodCompatibleSignature()`) is emitted as it. A typed or by-ref fixed arity gets `phelArityN` plus an `invokeArityN` forwarder; the variadic arity is `phelArityVariadic`.
- `__invoke` takes one optional param per position of the widest fixed arity (`$a0 = null, ...`) and dispatches with `match` on `func_num_args()`; the variadic arm reads `func_get_args()`. Not `...$args`: that packed every call into an array. Never test params for `null` to find the arity: `nil` is a real argument. The export wrapper depends on this shape (`.agnostic-ai/rules/module-interop.md`).
- Captures are constructor properties read at the top of each method. Never store arity bodies as closures in properties: each binds `$this`, so every runtime fn value (`comp`, `partial`) allocated a closure per arity plus a reference cycle.
- A fn captures only the enclosing locals its body references (`FnSymbol` via `LocalVarReferences`; all locals when the scanner returns null). The env inside the body still lists them all, so `MethodEmitter` calls `enterFnFrame()` with the left-out names and `emitFnWrapPrefix` skips them; a wrap that `use`s one reads an undefined variable.

## Call specialization

Tags come from the analyzer (`.agnostic-ai/rules/module-compiler-analyzer.md`, "Type tags"). Each family has two halves:

- Eligibility: a `OutputEmitter/*Specialization` class, aggregated by `CallSpecialization::isSpecialized()`.
- Emission: a `OutputEmitter/NodeEmitter/Specialized/*CallEmitter implements SpecializedCallEmitterInterface`, listed in `CallEmitter`, which loops `tryEmit()` before the generic path.

Predicates are disjoint, so order does not matter. A new family needs both halves registered.

`GlobalVarNode::hasFnMeta()` and `isRebindable()` own declaration and binding flags; `GlobalCallTarget` delegates to them, and `PhelCoreCall` rejects bindable targets before any core specialization. A local shadow is a `LocalVarNode`, which `PhelCoreCall::nameOf()` declines; `apply` and higher-order uses keep the runtime fn.

- Constructors: `ConstructorSpecialization` gates on the callee alone. `(vector …)`, `(hash-map …)` and friends become `\Phel::vector([…])` and so on. Odd counts lower on purpose: `\Phel::map([1])` raises the runtime fn's error.
- Multi-key `assoc`: on a typed map or vector, `AssocConjSpecialization::typedAssocPairs()` emits a `->put()` / `->update()` chain. Otherwise `CallEmitter` emits nested three-argument `assoc` steps outside `isSpecialized()`, keeping the call slot. Arguments evaluate once, left to right. `assoc!` always takes the untyped steps: on a persistent target it must throw.
- `get-in`: `GetInSpecialization` lowers any literal path. A tagged map or vector with two arguments unrolls to `($m[k] ?? null)`; otherwise `\Phel\Lang\GetIn::path(...)`, with an all-literal key array hoisted by `ConstantScope::reserveAsPhpArray()`. Never share that slot with an equal vector literal (a `PersistentVector`). A path with metadata keeps the runtime call.
- `assoc-in` / `update-in`: `AssocInSpecialization` lowers any literal path to `\Phel\Lang\AssocIn::path` or `::update`, sharing the same slot kind. No typed variant: `AssocIn` defers non-map, non-vector levels to `phel.core/get`/`assoc`, so transients, lists and bad indexes behave as at runtime.
- Guarded core calls: two-argument `<` `<=` `>` `>=` `=` `not=` `+` `-` `*` and one-argument `zero?` `pos?` `neg?` `even?` `odd?` `inc` `dec` that no typed family took become native PHP behind an `is_*` guard, runtime call as fallback (`GuardedCoreCallSpecialization`, `GuardedCoreCallEmitter`, run by `CallEmitter` outside `isSpecialized()`; the fallback takes the arity shortcut).
  - Each guard is the exact condition under which the runtime fn ends in the native operator.
  - Arithmetic keeps the native result only while it is an int: PHP yields a float exactly on overflow, which the runtime promotes to `BigInt`.
  - Locals and literals are read in place; other operands spill into a `$__phel_guard_N` temporary where PHP always evaluates it (`===` operands, `&` not `&&` between guards), so each runs once, in order. Only repeated reads of one local share a guard.
  - Quoted forms and collection literals decline.
- Only eager core fns lower to a native loop. `reduce` (3-arity) qualifies; `map`/`filter` do NOT: they return a `LazySeq` and copy the source's metadata, so a `foreach` would change the return type, break infinite seqs and shift side effects.

`IfChainMatchLowerer` shares literal-key eligibility and operand order between `case`/`cond` match lowering and direct `if` tests. Bindable equality targets keep runtime dispatch. Strict comparison runs first; an `EqualsInterface` subject keeps value equality (`BigInt`, noninterned keywords), and a keyword literal on the left keeps its own equality method. Match lowering captures its subject once and uses value equality only after every identity key misses. A bare `cond` rereads its local between fallback comparisons; `case` keeps its captured subject. `BodyConstantScanner` reserves consumed keyword keys by value and reserves no call slots for lowered equality tests.

`OutputEmitter/NodeEmitter/PhpObjectSetEmitter` shares `PhpObjectCallDispatchResolver` with the read path, so a class-name target is static however written, and prefixes a bare static member with `$`: a class constant is not assignable.

## Generated classes

`DefStructEmitter`, `DefInterfaceEmitter` and `DefEnumEmitter` read per-symbol metadata through `PhpAttributeEmitterTrait` (`:tag`, `:php/attr`, `:php/doc`, `^:php/override`) and the pure `Shared\PhpAttributeRenderer`. All opt-in: untagged forms emit unchanged. A list `:tag` is a union, a vector an intersection.

- `^:php/readonly` on a struct gives `readonly` typed properties (untagged: `readonly mixed`) and a constructor-rebuilding `put()`. The class stays a plain `final class`: `AbstractPersistentStruct` is not readonly.
- A `:php` marker opens a block of bare magic methods. `PhpBlockAnalyzer` carries it as a `DefStructInterface` with an empty name, which `DefStructEmitter` drops from `implements`. `enforceInvokeArity` is true only for structs: a custom `__invoke` must be 1-arg or variadic because a struct is a callable map.
- A trailing `:php/const` block in `definterface` emits typed constants (literal values only). The `definterface` macro wraps only method forms; the const block passes through to `definterface*`.
- `defenum` emits a native `enum` guarded by `enum_exists`; case values are all or none (backed vs pure). `InterfaceImplementationsAnalyzer` parses the tail (interfaces, methods, `:php` block) for `defstruct` and `defenum`.
- Export wrappers carry the same `:php/attr` via Interop's `CompiledPhpMethodBuilder`.
