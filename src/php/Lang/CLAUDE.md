# Lang Module

Core runtime type system: persistent data structures, language primitives, and collection protocols. No Gacela pattern: foundational leaf module; all types used directly by other modules. Depends only on `Phel\Shared` (`AbstractType::__toString()` uses `Printer::readable()`; `AbstractPersistentStruct` uses `Munge` for key encoding, lockstep with compiler).

## Core Types

**Symbols, Keywords, Variables**
- **Symbol**: names with optional namespace; special constants for language forms (`def`, `fn`, `if`, ...)
- **Keyword**: interned pool (identical keywords share instance); callable to access map values (`FnInterface`)
- **Atom**: mutable box with watches, validators, `deref` (Clojure-aligned; was `Variable`)
- **PhelVar**: first-class handle to global `def` (`deref`, `meta`, `alterRoot`, watches, `alterMeta`/`resetMeta`, cached `isDynamic`); callable via `__invoke` to current root. Produced by `Registry::addDefinition`/`getVar` and `(var sym)` form
- **PhelVarStateRegistry**: singleton side table for per-var watches, metadata, dynamic-flag cache keyed by `(ns, name)`; lets `PhelVar` stay `readonly` while `alter-meta!`/`add-watch` mutate canonical state. The `isDynamic` cache is cleared here via `invalidateDynamicCache(ns, name)` whenever metadata changes (`alter-meta!`/`reset-meta!` or re-`def`), not in `PhelVar`

**Numeric Types**
- **BigInt**: arbitrary-precision signed integer; base-10^9 with sign. Owns sign + metadata + signed semantics; delegates sign-agnostic digit-array kernels to **BigIntMagnitude** (stateless pure functions on `list<int>` digit arrays)
- **Ratio**: exact rational `n/d`; always normalized (denom > 0, gcd=1). `create($num, $den)` auto-collapses to `int`/`BigInt` if integral
- **BigDecimal**: arbitrary-precision signed decimal; mantissa * 10^-scale. Equality by value via `compareTo` (1.20M = 1.2M); `divideExact` extends scale to 100 digits. `__toString` omits the M suffix; REPL output appends M for round-trip. Literals: `1.5M`, `1.5e3M`

**Type Values**
- **UUID**: canonical 36-char UUID; `fromString` (validates, lowercases), `randomV4`, `version`, `variant`, value-based equality. Literal: `#uuid "..."`
- **PhpClass**: typed wrapper for PHP class/interface FQN; backs `phel.core/class`, `class?`, `class-name`

**Lazy and Mutable**
- **Delay**: single-value lazy computation; `deref()` runs the thunk once and caches (distinct from **LazySeq**, which is for sequences)
- **Volatile**: lightweight mutable container for transducer state (no watches/validators)
- **Reduced**: signals early termination from reduce/transduce
- **Future**: Amphp adapter exposing Phel deref/realized? protocol; **Eduction**: transducer composition helper

**Numeric Utilities**
- **NumericOperations**: static dispatch for arithmetic/comparisons/predicates across PHP numbers, `BigInt`, `Ratio`, `BigDecimal`. Owns only the contagion ladders; delegates type lifting to **NumericCoercion** and native-int overflow detection to **IntegerOverflow** (promotes int ops to `BigInt` on overflow)

Other utilities: `DynamicScope` (dynamic bindings), `Truthy`, `TypeStringifier`, `Hasher`/`Equalizer` (collection hashing/equality).

## Runtime Infrastructure

- **Registry**: singleton managing definitions by namespace (values + metadata)
- **TypeFactory**: singleton creating persistent collections; provides `Hasher`/`Equalizer` singletons
- **Seq**: static utility for sequence ops; **TagRegistry**: reader literal tag handler dispatch (`TagHandlers/`: `#inst`, `#uuid`, regex)
- **LoadClasspath**: static accessor for the `(load ...)` classpath, stored in `Registry` under `phel.core/*load-classpath*`. Lives here (not Compiler) because its state is a `Registry` slot; FQN baked into generated PHP by `LoadEmitter`; do not rename
- **Phel**: static helper for namespace/definition lookups (used by Api, Interop)

## Interfaces

- `TypeInterface` extends `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`
- `NamedInterface` (`getName`/`getNamespace`/`getFullName`); `FnInterface` (callable marker); `IdenticalInterface` (`===` equality)
- Collection capabilities compose as needed: `PushInterface`, `PopInterface`, `ConsInterface`, `CdrInterface`, `FirstInterface`, `ContainsInterface`, `RestInterface`, `ConcatInterface`, `SliceInterface`, `SeqInterface`, `AsTransientInterface`

## Collections

By subdirectory: `Map/` (PersistentArrayMap, PersistentHashMap, MapEntry), `SortedMap/`, `Vector/`, `LinkedList/` (PersistentList), `Queue/` (PersistentQueue: two-stack banker's queue, O(1) amortized; printed `<-(...)-<`), `HashSet/`, `SortedSet/`, `LazySeq/` (lazy chunking), `Struct/` (AbstractPersistentStruct), `Generators/` (sequence generators).

- **MapEntry**: equal by value to a 2-element vector (both directions); `first()` = key, `cdr()` = 1-vector with value
- **Transients**: `TransientVector`, `TransientMapWrapper`, `TransientSortedMap`/`TransientSortedSet`. All share `TransientStateTrait`: `persistent()` invalidates; mutators call `ensureTransientActive()` to guard reuse after `persistent!`

## Key Constraints

- All collections are persistent (immutable) with transient variants for bulk building
- `Registry`, `TypeFactory` are singletons; access via `getInstance()`
- `Registry` keys are dot-separated: `phel.core`, `my-app.lib` (after `-` → `_` munge). Compiler feeds through `Munge::encodeRegistryKey`
- `Symbol::getFullName` returns dot form; symbols with PHP class FQN namespace (leading `\`) keep backslash for static-method shorthand
- Source locations preserved via `SourceLocationInterface` for error reporting
- Numeric/value types (`BigInt`, `Ratio`, `BigDecimal`, `UUID`, `PhpClass`) are `final readonly` `TypeInterface` implementations with no I/O or static state
