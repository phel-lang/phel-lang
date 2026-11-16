---
name: module-lang
description: 'Load when changing Lang: runtime types, persistent collections, transients, registry or language primitives.'
scope: src/php/Lang
---

# Lang Module

Runtime types, persistent collections and language primitives. A leaf with no Gacela pattern: other modules use its types directly. It depends only on `Phel\Shared` (`AbstractType::__toString()` uses `Printer::readable()`; `AbstractPersistentStruct` encodes keys with `Munge`, in lockstep with the compiler).

## Do not rename

Generated PHP and cached artifacts call these FQNs: `Registry::readRoot` (`GlobalVarEmitter`), `GetIn` (`GetInCallEmitter`), `AssocIn` (`AssocInCallEmitter`), `Destructure` (`VectorBindingDeconstructor`), `LoadClasspath` (`LoadEmitter`). `LoadClasspath` lives here, not in Compiler, because its state is the `phel.core/*load-classpath*` slot in `Registry`.

## Boundaries

- `\Phel` (`src/Phel.php`, not Lang) is the root facade over `Registry`, used by Api and Interop. Lang must NOT call it (leaf to root cycle); use `Registry` or `TypeFactory`.
- `Registry`, `TypeFactory` and `PhelVarStateRegistry` are singletons: use `getInstance()`.
- `Registry` keys are dot-separated after the `-` to `_` munge (`phel.core`, `my_app.lib`); the compiler feeds them through `Munge::encodeRegistryKey`. `Symbol::getFullName` returns dot form, except a namespace that is a PHP class FQN (leading `\`) keeps the backslash for static-method shorthand.
- `Registry::readRoot(ns, name)` is the one-hop read compiled code uses for a var that is not `^:dynamic`/`^:redef`. `\Phel::getDefinition()` reads the same array behind a dynamic-scope gate that only a `^:dynamic` var trips. `DynamicScope::hasBoundName()` is the cheap gate before the fiber-local `hasBinding` lookup.
- `PhelType::nameOf()` mirrors `phel.core/type`. PHP code naming a type for a user reads it here, never `get_debug_type()` (it names the implementation; a map's name changes at eight entries). Branch order matches `predicates.phel`; `tests/phel/core/phel-type-parity.phel` pins it.

## Values

- `BigInt`, `Ratio`, `BigDecimal`, `UUID` and `PhpClass` are `final readonly` with no I/O or static state. `BigInt` owns sign and metadata; the sign-agnostic digit kernels live in `BigIntMagnitude`. `Ratio` is always normalized and `create()` collapses to `int`/`BigInt` when integral. `BigDecimal` is equal by `compareTo` (1.20M = 1.2M), and its `__toString` omits `M` (the REPL appends it).
- `NumericOperations` owns only the contagion ladders; lifting is in `NumericCoercion`, and `IntegerOverflow` promotes int ops to `BigInt`.
- `Keyword::create(string)` splits on the first `/` like `Symbol::create`; `createForNamespace` is verbatim.
- `Atom` compares by identity, never by dereferenced value.
- `MetaInterface::withMeta()` always returns a copy. Every concrete implementation carries `#[NoDiscard]`: PHP does not inherit it from the interface (`tests/php/Unit/Lang/Collections/NoDiscardTest.php`). `CopyLocationFromTrait` leaves `setStartLocation`/`setEndLocation` abstract so each type keeps its copy-vs-mutate strategy.

## Vars

- `PhelVar` is a `readonly` handle to a global `def`. `withMeta` returns a handle-local copy; `meta()` and mutators address canonical per-var state.
- That state (watches, metadata, dynamic-flag cache) lives in `PhelVarStateRegistry`, keyed by `(ns, name)`. On a metadata change (`alter-meta!`, `reset-meta!`, re-`def`) call `invalidateDynamicCache(ns, name)` there, NOT in `PhelVar`.

## Lazy values

- `LazySeq` caches the raw thunk result (`$realized` is `mixed`). Private `realizeSeq()` is the one narrowing point; it throws `NotASeqException` for a non-seq body like `(lazy-seq 5)`. A `nil` body is the empty seq.
- `Eduction` re-runs its chain on every consumption, so it has no size. `empty?`/`first` pull one element (`Seq::isEmpty`, `Seq::first`); `count` refuses with an actionable message.

## Compiled-path helpers

These answer exactly as the core fns they replace.

- `GetIn::path(ds, keys, notFound)`: maps and vectors inline, other `coll?`/array/string levels via registry `phel.core/get`; a transient or scalar level gives `notFound`.
- `AssocIn::path` / `AssocIn::update`: runtime's `get` down, `assoc` up order; a missing or nil level becomes `{}`. Maps and `int`-indexed vectors inline, other levels via registry `get`/`assoc`.
- `Destructure::nthNext(v, n)`: exactly `n` `cdr()` calls, so the tail keeps its `SubVector`, metadata and `nil` when exhausted.

## Collections

- `ValueIdentity::isSame()` is the unchanged-value test behind keyed writes that return the receiver (map `put`, vector `update`, struct `put`). It is identity with signed zero counted as a change, never `=`: an equal but distinguishable value (`-0.0`, a `BigInt`, a vector with other metadata) must still be written. A PHP array is never "same": `===` walks arrays, throws on recursive ones and misses references. An inline `===` before the call must check `is_array` first.
- `MapEntry` equals a 2-element vector both ways.
- `TransientStateTrait` (`persistent()` invalidates; mutators call `ensureTransientActive()`) sits on the object Phel code holds: `TransientVector`, `TransientMapWrapper` (returned by every `PersistentMap*::asTransient()`) and, redundantly, `TransientSortedMap`. Sets inherit it from their wrapper. Never add it to `TransientHashMap`/`TransientArrayMap`: user code never reaches them, and `TransientArrayMap::put()` must hand its contents to a fresh `TransientHashMap` on overflow. `tests/phel/core/transient-safety.phel` pins reuse-after-`persistent!` for all six flavors.
- A transient keeps its source's metadata and returns it on `persistent()` (`TransientMetaRoundTripTest`); `TransientArrayMap` copies it into the `TransientHashMap` it promotes to. Transients built from scratch have none. Map `merge`, vector/set `concat` and the compiler's `assoc`/`conj` specialization rely on this.
- Every transient is callable like its persistent counterpart (`__invoke` on the concrete class, not the interface).
- Sets share `HashSet\AbstractTransientSet` and `HashSet\AbstractPersistentSet`, both delegating to a map keyed by member.
- Choose the `merge()` strategy by trait, never by `instanceof` in the base. `AbstractPersistentMap::merge()` folds with `put()` (returns `$this` on an empty `$other`) for `PersistentSortedMap` and `AbstractPersistentStruct`; `PersistentHashMap`/`PersistentArrayMap` use `Map\TransientMergeStrategyTrait` (bulk build, always a fresh instance). Both keep the receiver's meta (`PersistentMapMergeTest`).
