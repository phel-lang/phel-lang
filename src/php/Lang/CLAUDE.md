# Lang Module

Core runtime type system: persistent data structures, language primitives, and collection protocols.

No Gacela pattern: foundational leaf module; all types used directly by other modules. Depends only on `Phel\Shared`:
- `AbstractType::__toString()` uses `Printer::readable()`.
- `AbstractPersistentStruct` uses `Munge` for key encoding (lockstep with compiler).

## Scalar / Value Types

| Type | Notes |
|------|-------|
| `Symbol` | Names with optional namespace; special constants for language forms (`def`, `fn`, `if`, ...) |
| `Keyword` | Interned pool keyed by (ns, name) so identical keywords share an instance; `create(string)` splits the name on the first `/` like `Symbol::create`, `createForNamespace` is verbatim; callable via `FnInterface` to access map values |
| `Atom` | Mutable box with watches, validators, `deref` (Clojure-aligned; formerly `Variable`). Compares by identity (`===`), never by dereferenced value |
| `BigInt` | Arbitrary-precision signed integer (base-10^9 + sign). Owns sign/metadata/signed semantics; delegates sign-agnostic digit-array kernels to `BigIntMagnitude` (stateless pure fns on `list<int>`) |
| `Ratio` | Exact rational `n/d`, always normalized (denom > 0, gcd=1). `create($num, $den)` auto-collapses to `int`/`BigInt` if integral |
| `BigDecimal` | Arbitrary-precision signed decimal (mantissa * 10^-scale). Equality by value via `compareTo` (1.20M = 1.2M); `divideExact` extends scale to 100 digits. `__toString` omits `M` suffix; REPL appends `M` for round-trip. Literals: `1.5M`, `1.5e3M` |
| `UUID` | Canonical 36-char; `fromString` (validates, lowercases), `randomV4`, `version`, `variant`, value-based equality. Literal: `#uuid "..."` |
| `PhpClass` | Typed wrapper for PHP class/interface FQN; backs `phel.core/class`, `class?`, `class-name` |

## Vars

| Type | Notes |
|------|-------|
| `PhelVar` | First-class handle to global `def`: `deref`, `meta`, `alterRoot`, watches, `alterMeta`/`resetMeta`, cached `isDynamic`; callable via `__invoke` to current root. Produced by `Registry::addDefinition`/`getVar` and `(var sym)` |
| `PhelVarStateRegistry` | Singleton side table for per-var watches, metadata, dynamic-flag cache keyed by `(ns, name)`. Lets `PhelVar` stay `readonly` while `alter-meta!`/`add-watch` mutate canonical state. Clear `isDynamic` cache via `invalidateDynamicCache(ns, name)` on metadata change (`alter-meta!`/`reset-meta!`/re-`def`) — done here, NOT in `PhelVar` |

## Lazy / Mutable / Control

| Type | Notes |
|------|-------|
| `Delay` | Single-value lazy computation; `deref()` runs thunk once and caches (distinct from `LazySeq`) |
| `Volatile` | Lightweight mutable container for transducer state (no watches/validators) |
| `Reduced` | Signals early termination from reduce/transduce |
| `Future` | Amphp adapter exposing Phel deref/realized? protocol |
| `Eduction` | Transducer composition helper |

## Numeric Utilities

- `NumericOperations`: static dispatch for arithmetic/comparisons/predicates across PHP numbers, `BigInt`, `Ratio`, `BigDecimal`. Owns only the contagion ladders.
- `NumericCoercion`: type lifting (delegated from `NumericOperations`).
- `IntegerOverflow`: native-int overflow detection; promotes int ops to `BigInt` on overflow.

Other utilities: `DynamicScope` (dynamic bindings), `Truthy`, `TypeStringifier`, `Hasher`/`Equalizer` (collection hashing/equality).

## Runtime Infrastructure

| Class | Notes |
|-------|-------|
| `Registry` | Singleton managing definitions by namespace (values + metadata) |
| `TypeFactory` | Singleton creating persistent collections; provides `Hasher`/`Equalizer` singletons |
| `Seq` | Static utility for sequence ops |
| `TagRegistry` | Reader literal tag-handler dispatch (`TagHandlers/`: `#inst`, `#uuid`, regex) |
| `LoadClasspath` | Static accessor for the `(load ...)` classpath, stored in `Registry` under `phel.core/*load-classpath*`. Lives here (not Compiler) because its state is a `Registry` slot; FQN baked into generated PHP by `LoadEmitter`. Do NOT rename |
| `\Phel` (`src/Phel.php`, NOT a Lang class) | Thin root facade proxying static calls to the `Registry` singleton via `__callStatic`. Api/Interop use it for ns/definition lookups (`getNamespaces`, `getDefinition`, `getDefinitionMetaData`). Lang's own code must NOT call it (leaf → root cycle); use `Registry`/`TypeFactory` directly |

## Interfaces

- `TypeInterface` extends `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`.
- `NamedInterface` (`getName`/`getNamespace`/`getFullName`); `FnInterface` (callable marker); `IdenticalInterface` (`===` equality).
- Collection capabilities compose as needed: `PushInterface`, `PopInterface`, `ConsInterface`, `CdrInterface`, `FirstInterface`, `ContainsInterface`, `RestInterface`, `ConcatInterface`, `SliceInterface`, `SeqInterface`, `AsTransientInterface`.

## Collections (`Collections/`)

| Subdir | Contents |
|--------|----------|
| `Map/` | `PersistentArrayMap`, `PersistentHashMap`, `MapEntry` |
| `SortedMap/` | Sorted map variant |
| `Vector/` | `PersistentVector` |
| `LinkedList/` | `PersistentList` |
| `Queue/` | `PersistentQueue` — two-stack banker's queue, O(1) amortized; printed `<-(...)-<` |
| `HashSet/`, `SortedSet/` | Set variants |
| `LazySeq/` | Lazy chunking |
| `Struct/` | `AbstractPersistentStruct` |
| `Generators/` | Sequence generators |

- `MapEntry`: equal by value to a 2-element vector (both directions); `first()` = key, `cdr()` = 1-vector with value.
- Transients: `TransientVector`, `TransientMapWrapper`, `TransientSortedMap`/`TransientSortedSet`. All share `TransientStateTrait`: `persistent()` invalidates; mutators call `ensureTransientActive()` to guard reuse after `persistent!`.

## Key Constraints

- All collections are persistent (immutable) with transient variants for bulk building.
- `Registry`, `TypeFactory`, `PhelVarStateRegistry` are singletons; access via `getInstance()`.
- `Registry` keys are dot-separated: `phel.core`, `my-app.lib` (after `-` → `_` munge). Compiler feeds through `Munge::encodeRegistryKey`.
- `Symbol::getFullName` returns dot form; symbols with a PHP class FQN namespace (leading `\`) keep the backslash for static-method shorthand.
- Source locations preserved via `SourceLocationInterface` for error reporting.
- Numeric/value types (`BigInt`, `Ratio`, `BigDecimal`, `UUID`, `PhpClass`) are `final readonly` `TypeInterface` implementations with no I/O or static state.
