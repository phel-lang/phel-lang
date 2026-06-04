# Lang Module

Core runtime type system: persistent data structures, language primitives, and collection protocols.

## No Gacela Pattern

Foundational leaf module with no Facade, Factory, or DependencyProvider. All types used directly by other modules.

## Core Types

**Symbols, Keywords, Variables**
- **Symbol**: names with optional namespace; special constants for language forms (`def`, `fn`, `if`, etc.)
- **Keyword**: interned pool; callable to access map values; implements `FnInterface`
- **Atom**: mutable box with watches, validators, `deref` (Clojure-aligned; was `Variable`)
- **PhelVar**: first-class handle to global `def`. Methods: `deref`, `meta`, `alterRoot`, `addWatch`/`removeWatch`, `alterMeta`/`resetMeta`, `isDynamic` (cached). Callable via `__invoke` to current root. Produced by `Registry::addDefinition`/`getVar` and `(var sym)` form
- **PhelVarStateRegistry**: singleton side table for per-var watches, metadata, dynamic-flag cache keyed by `(ns, name)`; enables `PhelVar` to stay `readonly` while `alter-meta!` and `add-watch` mutate canonical state. The `isDynamic` cache is cleared here via `invalidateDynamicCache(ns, name)` whenever metadata changes (`alter-meta!`/`reset-meta!` or a re-`def`), not in `PhelVar` itself

**Numeric Types**
- **BigInt**: arbitrary-precision signed integer (`final readonly`, `TypeInterface`); base-10^9 with sign. Methods: `fromInt`, `fromFloat`, `fromString`, `add`/`subtract`/`multiply`/`divide`/`mod`/`gcd`/`pow`/`negate`/`abs`, `compareTo`, `equals`, `hash`, `toInt`, `fitsInPhpInt`. No I/O or static state. Owns sign + metadata + signed semantics; delegates the sign-agnostic digit-array kernels to **BigIntMagnitude**
- **BigIntMagnitude**: stateless base-10^9 magnitude arithmetic (`compare`/`add`/`subtract`/`multiply`/`divMod`/`trim`, plus `split`/`fromDecimalDigits`/`toDecimalString` conversions). Pure functions on `list<int>` digit arrays; the unsigned kernel under `BigInt`
- **Ratio**: exact rational `n/d` (`final readonly`, `TypeInterface`); always normalized (denom > 0, gcd=1). `create($num, $den)` auto-collapses to `int`/`BigInt` if integral. Arithmetic preserves type over reals
- **BigDecimal**: arbitrary-precision signed decimal (`final readonly`, `TypeInterface`); mantissa * 10^-scale. Constructors: `fromString`, `fromInt`, `fromBigInt`, `fromFloat` (shortest round-trip). Methods: `add`/`subtract`/`multiply`/`divideExact`/`negate`/`abs`/`compareTo`/`isZero`. Equality by value via `compareTo` (1.20M = 1.2M). `divideExact` extends scale to 100 digits. `__toString` outputs scale-respecting form without M suffix; `toPlainString` alias. REPL output appends M suffix for round-trip. Literals: `1.5M`, `1.5e3M`

**Type Values**
- **UUID**: canonical 36-char UUID (`final readonly`, `TypeInterface`). Methods: `fromString` (validates, lowercases), `randomV4`, `version`, `variant` (ncs, rfc-4122, microsoft, reserved), `isNil`, value-based `equals`/`hash`. Literal: `#uuid "..."`
- **PhpClass**: typed wrapper for PHP class/interface FQN (`final readonly`, `TypeInterface`). Methods: `fromName` (validates, strips leading \), `ofValue` (get object class), `isInstance` (runtime `is_a`). Backs `phel.core/class`, `class?`, `class-name`

**Collections (full types listed below)**
- **PersistentQueue**: persistent FIFO (`final readonly`, `TypeInterface, Countable, IteratorAggregate, FirstInterface, CdrInterface, ConsInterface, PushInterface, PopInterface`). Two-stack banker's queue; O(1) amortized push/peek/pop. Constructor: `empty`, `fromArray`. `cons` alias for `push`. Type: `:queue`
- **MapEntry**: typed two-element entry (`final readonly`, `TypeInterface, Countable, IteratorAggregate, FirstInterface, CdrInterface`). Equal by value to 2-element vector (both directions). Accessors: `key()`/`value()`; `first()` = key, `cdr()` = 1-vector with value

**Lazy and Mutable**
- **Delay**: single-value lazy computation; `deref()` runs the thunk once and caches the result, `isRealized()` reports whether it has run. Use for single-value laziness (distinct from **LazySeq**, which is for sequences)
- **Volatile**: lightweight mutable container for transducer state (no watches/validators)
- **Reduced**: signals early termination from reduce/transduce
- **Future**: Amphp adapter; exposes Phel deref/realized? protocol
- **Eduction**: transducer composition helper
- **LazySeq**: lazy sequence implementation with chunking

**Utilities**
- **NumericOperations**: static dispatch for `+`/`-`/`*`/`/`, comparisons, predicates across PHP numbers, `BigInt`, `Ratio`, `BigDecimal`. Owns only the contagion ladders; delegates type lifting to **NumericCoercion** and native-int overflow detection to **IntegerOverflow**
- **NumericCoercion**: stateless numeric type lifting/validation (`ensureNumeric`, `toFloat`/`toBigInt`/`toBigDecimal`, `rationalOperand`, `collapseBigInt`, `toIntExponent`, `truncateToInt`) shared by `NumericOperations`
- **IntegerOverflow**: pure native-int overflow predicates (`onAdd`/`onSubtract`/`onMultiply`); tells `NumericOperations` when to promote an int op to `BigInt`
- **DynamicScope**: dynamic variable binding context
- **Truthy**: coercion to boolean
- **TypeStringifier**: `__toString` rendering
- **Hasher**, **Equalizer**: collection hashing and equality; `HasherInterface`, `EqualizerInterface`

## Runtime Infrastructure

- **Registry**: singleton managing definitions by namespace (values + metadata); `getInstance()`
- **TypeFactory**: singleton creating persistent collections; provides `Hasher` and `Equalizer` singletons
- **Seq**: static utility for sequence ops (map, filter, take, drop, partition)
- **TagRegistry**: reader literal tag handler dispatch
- **LoadClasspath**: static accessor for the `(load ...)` classpath, stored in `Registry` under `phel.core/*load-classpath*`. Lives here (not Compiler) because its state is a `Registry` slot; `publish()`/`read()` consumed by Run, Build, and the emitted `(load ...)` lookup. FQN baked into generated PHP by `LoadEmitter`; do not rename.
- **Phel**: static helper for namespace/definition lookups (used by Api, Interop)

## Interface Hierarchy

**Core**
- `TypeInterface`: extends `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`
- `NamedInterface`: `getName()`, `getNamespace()`, `getFullName()`
- `FnInterface`: marker for callable types
- `IdenticalInterface`: identity-based equality (`===`)
- `EqualsInterface`, `HashableInterface`: value-based equality and hashing

**Collection Capabilities** (compose as needed)
- `PushInterface`, `PopInterface`, `ConsInterface`, `CdrInterface`, `FirstInterface`
- `ContainsInterface`, `RestInterface`, `ConcatInterface`, `SliceInterface`
- `SeqInterface`: iterate; `AsTransientInterface`: convert to transient

## Collections Subdirectory Structure

| Name | Location | Interfaces |
|------|----------|-----------|
| **PersistentArrayMap** | `Map/` | `PersistentMapInterface` |
| **PersistentHashMap** | `Map/` | `PersistentMapInterface` |
| **PersistentSortedMap** | `SortedMap/` | `PersistentMapInterface` (sorted keys) |
| **MapEntry** | `Map/` | `TypeInterface, FirstInterface, CdrInterface` |
| **PersistentVector** | `Vector/` | `PersistentVectorInterface` |
| **PersistentList** | `LinkedList/` | `PersistentListInterface` |
| **PersistentQueue** | `Queue/` | `TypeInterface` (FIFO) |
| **PersistentHashSet** | `HashSet/` | `PersistentHashSetInterface` |
| **PersistentSortedSet** | `SortedSet/` | `PersistentHashSetInterface` (sorted elements) |
| **LazySeq** | `LazySeq/` | `LazySeqInterface` (lazy chunking) |
| **AbstractPersistentStruct** | `Struct/` | struct key encoding via `Phel\Shared\Munge` |

**Transients**: `TransientVector`, `TransientMapWrapper` (array/hash maps, hash-sets), `TransientSortedMap`/`TransientSortedSet` (sorted maps/sets via delegation). All share `TransientStateTrait`: `persistent()` invalidates; mutators call `ensureTransientActive()` to guard reuse after `persistent!`

## Generators

Sequence generators in `Generators/`: `CombineGenerator`, `DedupeGenerator`, `FileGenerator`, `InfiniteGenerator`, `PartitionGenerator`, `SequenceGenerator`, `SliceGenerator`, `TransformGenerator`

## Tag Handlers

Reader literals in `TagHandlers/`: builtin handlers for `#inst`, `#uuid`, regex literals. `AbstractStringTagHandler`, `BuiltinTagHandlers`, `InstTagHandler`, `RegexTagHandler`, `UUIDTagHandler`

## Dependencies

**Leaf module**; depends only on `Phel\Shared` (itself a leaf):
- `AbstractType::__toString()` calls `Phel\Shared\Printer\Printer::readable()` for collection rendering
- `AbstractPersistentStruct` uses `Phel\Shared\Munge` for key encoding (lockstep with compiler)

## Used By

Every module: Compiler (AST), Printer, Build (namespace extraction), Api, Interop, Run (evaluation)

## Key Constraints

- All collections are **persistent** (immutable) with transient variants for bulk building
- `Keyword` intern pool; identical keywords share instance for memory efficiency
- `Registry`, `TypeFactory` are singletons; access via `getInstance()`
- `Registry` keys are dot-separated: `phel.core`, `my-app.lib` (after `-` → `_` munge). Compiler feeds through `Munge::encodeRegistryKey`
- `Symbol::getFullName` returns dot form; symbols with PHP class FQN namespace (leading `\`) keep backslash for static-method shorthand
- Source locations preserved via `SourceLocationInterface` for error reporting
- `PhelVar` implements `FnInterface` so handles are callable
