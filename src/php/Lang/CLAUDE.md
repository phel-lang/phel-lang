# Lang Module

Core runtime type system: persistent data structures, language primitives, and collection protocols.

## No Gacela Pattern

This is a **foundational module** with no Facade, Factory, or DependencyProvider. Types are used directly by all other modules.

## Core Types

- **Symbol** — names with optional namespace; special constants for language forms (`def`, `fn`, `if`, etc.)
- **Keyword** — interned with pool; callable as functions to access map values (implements `FnInterface`)
- **Variable** — mutable box (atom) with watches, validators, deref
- **PhelVar** — first-class handle to a global definition (`def`); produced by `Registry::addDefinition`/`getVar` and the `(var sym)` / `#'sym` forms; offers `deref`, `meta`, `alterRoot`, `addWatch`/`removeWatch`, `alterMeta`/`resetMeta`, and cached `isDynamic`; implements `FnInterface` so handles are callable (`__invoke` forwards to the current root value)
- **PhelVarStateRegistry** — singleton side table for per-var watches, metadata overrides, and dynamic-flag cache keyed by `(ns, name)`; lets `PhelVar` stay `readonly` while `alter-meta!` / `add-watch` mutate canonical state
- **BigInteger** — pure-PHP arbitrary-precision signed integer (`final readonly`); base-10^9 magnitude with sign; `fromInt`, `fromFloat` (truncate toward zero, reject NaN/Inf), `fromString`, `add/subtract/multiply/divide/mod/gcd/pow/negate/abs`, `compareTo/equals`, `hash`, `toInt`/`fitsInPhpInt`. Implements `TypeInterface` (with optional source location and meta) so `N`-suffix literals beyond `PHP_INT_MAX` flow through reader/analyzer/emitter as first-class values. No I/O, no static state.
- **Rational** — exact rational `n/d` (`final readonly`, implements `TypeInterface`); always normalised (denominator > 0, `gcd(|num|, denom) = 1`). `Rational::create($num, $den)` auto-collapses to `int` or `BigInteger` when the result is integral, so `create(4, 2)` returns `int 2`, not a `Rational`. Arithmetic accepts `Rational | BigInteger | int` and stays `Rational` only while the result is non-integral.
- **Uuid** — canonical 36-char UUID value (`final readonly`, implements `TypeInterface`); `Uuid::fromString` validates + lowercases, `Uuid::randomV4` generates a v4 value. Methods: `version`, `variant` (returns one of `ncs|rfc-4122|microsoft|reserved`), `isNil`, value-based `equals`/`hash`. `#uuid "..."` reader literal and `phel.core/random-uuid` produce `Uuid` instances; `LiteralEmitter` emits them as `Uuid::fromString(...)` calls.
- **NumericOperations** — runtime dispatch helper (static-only, `final`) for `+ - * /`, comparisons, and predicates across native PHP numbers, `BigInteger`, and `Rational`. Phel's arithmetic core fns route through this class because PHP's native operators do not dispatch on objects.
- **Delay** — lazy evaluation with caching
- **Volatile** — lightweight mutable container for transducer state
- **Reduced** — signals early termination from reduce/transduce

## Runtime Infrastructure

- **Registry** (singleton) — manages definitions organized by namespace (values + metadata)
- **TypeFactory** (singleton) — creates persistent collections; provides `Hasher` and `Equalizer`
- **Seq** — static utility for sequence/generator operations (map, filter, take, drop, partition)
- **Phel** — static helper for namespace/definition lookups (used by Api, Interop)

## Interface Hierarchy

**Top-level**: `TypeInterface` extends `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`

**Named**: `NamedInterface` — `getName()`, `getNamespace()`, `getFullName()`
**Callable**: `FnInterface` — marker for callable types
**Identity**: `IdenticalInterface` — identity-based equality (`===`)

**Collection capabilities** (compose together):
`PushInterface`, `PopInterface`, `ContainsInterface`, `FirstInterface`, `CdrInterface`, `ConsInterface`, `RestInterface`, `ConcatInterface`, `SliceInterface`, `SeqInterface`, `AsTransientInterface`

## Collections (`Collections/` subdirectory)

- **Map/** — `PersistentArrayMap`, `PersistentHashMap` (implements `PersistentMapInterface`)
- **Vector/** — `PersistentVector` (implements `PersistentVectorInterface`)
- **LinkedList/** — `PersistentList` (implements `PersistentListInterface`)
- **HashSet/** — `PersistentHashSet` (implements `PersistentHashSetInterface`)
- **LazySeq/** — `LazySeq` (implements `LazySeqInterface`)
- **Struct/** — `AbstractPersistentStruct`, `StructKeyEncoder` (mirrors compiler name-mangling)

## Dependencies

This module aims to be a **leaf module** with no dependencies on other modules.

The only remaining outbound import is `AbstractType::__toString()` calling `Phel\Printer\Printer::readable()` to render collection types; collapsing that edge requires installing a printer adapter at bootstrap and is tracked separately.

## Used By

Every module: Compiler (AST representation), Printer (value display), Build (namespace extraction), Api (introspection), Interop (runtime interop), Run (evaluation).

## Key Constraints

- All collection types are **persistent** (immutable) with transient variants for bulk building
- `Keyword` uses an intern pool for memory efficiency — identical keywords share the same instance
- `Registry` is a singleton; `TypeFactory` is a singleton — both use `getInstance()`
- Keyword callability, ex-info exceptions, atom-style mutation on `Variable`, first-class `PhelVar` for global defs
- Source locations must be preserved via `SourceLocationInterface` for error reporting
- `Registry` keys are dot-separated: `phel.core`, `my-app.lib` (after `-` → `_` munge → `my_app.lib`). Compiler emitters and analyzer feed the registry through `Munge::encodeRegistryKey`. `Symbol::getFullName` returns the dot form for Phel symbols; symbols whose namespace is a PHP class FQN (leading `\`) keep backslash so static-method shorthand still resolves.
