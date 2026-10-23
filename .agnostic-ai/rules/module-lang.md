---
name: module-lang
description: 'Lang module: runtime types, persistent collections and language primitives.'
scope: src/php/Lang
---

# Lang Module

Runtime types, persistent collections, language primitives. A leaf module with no Gacela pattern; other modules use its types directly. Depends only on `Phel\Shared` (`AbstractType::__toString()` uses `Printer::readable()`; `AbstractPersistentStruct` uses `Munge` for key encoding, in lockstep with the compiler).

## Scalar / Value Types

| Type | Notes |
|------|-------|
| `Symbol` | Optional namespace; constants for language forms (`def`, `fn`, `if`, ...) |
| `Keyword` | Interned by (ns, name). `create(string)` splits on the first `/` like `Symbol::create`; `createForNamespace` is verbatim. Callable (`FnInterface`) to read map values |
| `Atom` | Watches, validators, `deref`, `alterMeta`/`resetMeta` (formerly `Variable`); `withMeta` copies. Compares by identity (`===`), never by dereferenced value |
| `BigInt` | Base 10^9 + sign. Owns sign/metadata semantics; sign-agnostic digit kernels live in `BigIntMagnitude` (stateless, on `list<int>`) |
| `Ratio` | Always normalized (denom > 0, gcd 1). `create($num, $den)` collapses to `int`/`BigInt` when integral |
| `BigDecimal` | Mantissa * 10^-scale. Equal by `compareTo` (1.20M = 1.2M); `divideExact` extends scale to 100 digits. `__toString` omits `M`; the REPL appends it. Literals `1.5M`, `1.5e3M` |
| `UUID` | `fromString` (validates, lowercases), `randomV4`, `version`, `variant`, value equality. Literal `#uuid "..."` |
| `PhpClass` | PHP class/interface FQN; backs `phel.core/class`, `class?`, `class-name` |

`BigInt`, `Ratio`, `BigDecimal`, `UUID`, `PhpClass` are `final readonly` `TypeInterface` implementations with no I/O or static state.

## Vars

- `PhelVar`: handle to a global `def` (`deref`, `meta`, `alterRoot`, watches, `alterMeta`/`resetMeta`, cached `isDynamic`; `__invoke` calls the root). `withMeta` returns a handle-local copy; `meta()` and mutators address canonical per-var state. Made by `Registry::addDefinition`/`getVar` and `(var sym)`.
- `PhelVarStateRegistry`: singleton side table (watches, metadata, dynamic-flag cache) keyed by `(ns, name)`, so `PhelVar` stays `readonly`. On metadata change (`alter-meta!`/`reset-meta!`/re-`def`) call `invalidateDynamicCache(ns, name)` here, NOT in `PhelVar`.

## Lazy / Mutable / Control

- `Delay`: runs its thunk once on `deref()` and caches.
- `Collections/LazySeq/LazySeq`: caches the raw thunk result (`$realized` is `mixed`). Private `realizeSeq()` is the single narrowing point; it throws `Collections\Exceptions\NotASeqException` for a non-seq body like `(lazy-seq 5)`. A `nil` body is the empty seq.
- `Volatile` (transducer state, no watches/validators), `Reduced` (early termination), `Future` (Amphp adapter for deref/realized?).
- `Eduction`: re-runs its chain on every consumption, so it has no size. `empty?`/`first` pull one element (`Seq::isEmpty`, `Seq::first`); `count` refuses with an actionable message rather than draining it.

## Numeric and Other Utilities

- `NumericOperations`: static arithmetic/comparison/predicate dispatch across PHP numbers, `BigInt`, `Ratio`, `BigDecimal`; owns only the contagion ladders. Lifting is in `NumericCoercion`; `IntegerOverflow` promotes int ops to `BigInt`.
- `DynamicScope`: bound-name counts are private; `hasBoundName(ns, name)` is the cheap gate before the fiber-local `hasBinding` lookup.
- Also: `Truthy`, `TypeStringifier`, `Hasher`/`Equalizer`.
- Traits: `MetaTrait`, `HashCombinerTrait`, `CopyLocationFromTrait` (default `copyLocationFrom()`; `setStartLocation`/`setEndLocation` stay abstract so each type keeps its copy-vs-mutate strategy).

## Runtime Infrastructure

| Class | Notes |
|-------|-------|
| `Registry` | Definitions by namespace (values + metadata). `readRoot(ns, name)` is the one-hop read compiled code uses for a var that is not `^:dynamic`/`^:redef`; `\Phel::getDefinition()` reads the same array behind a dynamic-scope gate only a `^:dynamic` var trips (#3179). Profiler state is private, behind `getProfilerHook()`/`setProfilerHook()` |
| `TypeFactory` | Creates persistent collections; provides `Hasher`/`Equalizer` singletons |
| `Seq` | Sequence ops, mostly delegating to `Generators/`, plus the single-pull probes `isEmpty()`/`first()` for a source with no size or indexed access |
| `TagRegistry` | Reader tag dispatch (`TagHandlers/`: `#inst`, `#uuid`, regex) |
| `PhelType` | `nameOf(mixed)`, PHP mirror of `phel.core/type`. PHP code naming a type for a user reads it here, never `get_debug_type()` (names the implementation; a map's name changes at eight entries). Branch order matches `predicates.phel`; `tests/phel/core/phel-type-parity.phel` pins them (#3290) |
| `GetIn` | `path(ds, keys, notFound)`, compiled literal-path `get-in` (#3320), answering exactly as `phel.core/get-in`: maps and vectors inline, other `coll?`/array/string levels via registry `phel.core/get`, a transient or scalar level gives `notFound` |
| `AssocIn` | `path(ds, keys, v)`, `update(ds, keys, f, ...args)`: compiled literal-path `assoc-in`/`update-in` (#3328). Runtime's `get` down, `assoc` up order; a missing or nil level becomes `{}`. Maps and `int`-indexed vectors inline, other levels via registry `phel.core/get`/`assoc`, so it answers or throws as the runtime does |
| `Destructure` | `nthNext(v, n)`, the `& r` tail of `[a b & r]` with two or more positions before `&` (#3356). Exactly `n` `cdr()` calls, so the tail keeps its `SubVector`, metadata and `nil`-when-exhausted |
| `LoadClasspath` | `(load ...)` classpath, kept in `Registry` under `phel.core/*load-classpath*` (hence here, not Compiler) |
| `\Phel` (`src/Phel.php`, NOT Lang) | Root facade proxying statics to `Registry`; Api/Interop use it (`getNamespaces`, `getDefinition`, `getDefinitionMetaData`). Lang must NOT call it (leaf to root cycle); use `Registry`/`TypeFactory` |

Do NOT rename these; generated PHP and cached artifacts call their FQNs: `Registry::readRoot` (`GlobalVarEmitter`), `GetIn` (`GetInCallEmitter`), `AssocIn` (`AssocInCallEmitter`), `Destructure` (`VectorBindingDeconstructor`), `LoadClasspath` (`LoadEmitter`).

## Interfaces

- `TypeInterface` extends `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`.
- `NamedInterface`, `FnInterface` (callable marker), `IdenticalInterface` (`===` equality).
- Collection capabilities compose: `PushInterface`, `PopInterface`, `ConsInterface`, `CdrInterface`, `FirstInterface`, `ContainsInterface`, `RestInterface`, `ConcatInterface`, `SliceInterface`, `SeqInterface`, `AsTransientInterface`.

## Collections (`Collections/`)

Subdirs: `Map/` (`PersistentArrayMap`, `PersistentHashMap`, `MapEntry`), `SortedMap/`, `Vector/`, `LinkedList/`, `Queue/` (`PersistentQueue`, two-stack banker's queue, O(1) amortized, printed `<-(...)-<`), `HashSet/`, `SortedSet/`, `LazySeq/`, `Struct/` (`AbstractPersistentStruct`), `Generators/`.

- `ValueIdentity::isSame()` is the unchanged-value test behind keyed writes that return the receiver (map `put`, vector `update`, struct `put`). Identity with signed zero counted as a change, never `=`: an equal but distinguishable value (`-0.0`, a `BigInt`, a vector with other metadata) must still be written (#3321). A PHP array is never "same": `===` walks arrays, throws on recursive ones and misses references. An inline `===` before the call (`PersistentVector` keeps one for speed) must check `is_array` first.
- `MapEntry` equals a 2-element vector both ways; `first()` = key, `cdr()` = 1-vector of the value.
- `TransientStateTrait` (`persistent()` invalidates; mutators call `ensureTransientActive()`) sits on the object Phel code holds:
  - `TransientVector` uses it; `PersistentVector::asTransient()` returns it unwrapped.
  - `TransientMapWrapper` uses it; every `PersistentMap*::asTransient()` returns one. `TransientHashMap`/`TransientArrayMap` deliberately do **not**: user code never reaches them, and `TransientArrayMap::put()` must hand its contents to a fresh `TransientHashMap` on overflow. `TransientSortedMap` also uses it, redundantly but harmlessly.
  - `TransientHashSet`/`TransientSortedSet` inherit the guard from their `TransientMapWrapper`.

  Reuse after `persistent!` throws for all six flavours (`tests/phel/core/transient-safety.phel`). Do not add the trait to the inner map classes.
- A transient keeps its source collection's metadata and returns it on `persistent()` (`TransientMetaRoundTripTest`); `TransientArrayMap` copies it into the `TransientHashMap` it promotes to. Transients built from scratch (`TransientVector::empty`, `PersistentHashMap::fromArray`) have none. Map `merge`, vector/set `concat` and the compiler's `assoc`/`conj` chain specialisation rely on this.
- Every transient is callable like its persistent counterpart (`__invoke` on the concrete class, not the interface).
- Sets share bases: `HashSet\AbstractTransientSet` (a facade over a transient map keyed by member; subclasses add only `__toString()`, `persistent()`) and `HashSet\AbstractPersistentSet` (map-delegating half; subclasses own `withMeta`, `add`, `remove`, `asTransient`, plus `getEffectiveComparator` when sorted).
- `merge()` strategy is chosen by trait, never by `instanceof` in the base. `AbstractPersistentMap::merge()` folds with `put()` (returns `$this` on empty `$other`) for `PersistentSortedMap` and `AbstractPersistentStruct`; `PersistentHashMap`/`PersistentArrayMap` use `Map\TransientMergeStrategyTrait` (bulk build, always a fresh instance). Both keep the receiver's meta (`PersistentMapMergeTest`).

## Key Constraints

- All collections are persistent (immutable), with transients for bulk building.
- `Registry`, `TypeFactory`, `PhelVarStateRegistry` are singletons; use `getInstance()`.
- `Registry` keys are dot-separated: `phel.core`, `my-app.lib` (after `-` to `_` munge). The compiler feeds them through `Munge::encodeRegistryKey`.
- `Symbol::getFullName` returns dot form; a namespace that is a PHP class FQN (leading `\`) keeps the backslash for static-method shorthand.
- Source locations travel via `SourceLocationInterface` for error reporting.
- `MetaInterface::withMeta()` always returns a copy; every implementation carries `#[NoDiscard]`.
