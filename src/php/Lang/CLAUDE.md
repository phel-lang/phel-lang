# Lang Module

Core runtime type system: persistent data structures, language primitives, and collection protocols.

## No Gacela Pattern

This is a **foundational module** with no Facade, Factory, or DependencyProvider. Types are used directly by all other modules.

## Core Types

- **Symbol** — names with optional namespace; special constants for language forms (`def`, `fn`, `if`, etc.)
- **Keyword** — interned with pool; callable as functions to access map values (implements `FnInterface`)
- **Variable** — mutable box with watches, validators, deref (Clojure-like)
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
- **Struct/** — `AbstractPersistentStruct`

## Dependencies

None. This is a **leaf module** — it depends on nothing else in the project.

## Used By

Every module: Compiler (AST representation), Printer (value display), Build (namespace extraction), Api (introspection), Interop (runtime interop), Run (evaluation).

## Key Constraints

- All collection types are **persistent** (immutable) with transient variants for bulk building
- `Keyword` uses an intern pool for memory efficiency — identical keywords share the same instance
- `Registry` is a singleton; `TypeFactory` is a singleton — both use `getInstance()`
- Clojure-aligned semantics: Keyword callability, ex-info exceptions, Variable with watches
- Source locations must be preserved via `SourceLocationInterface` for error reporting
