# ADR 0011: Persistent collections implemented in PHP

- **Status**: Accepted (recorded retroactively; the decision predates this record)
- **Date**: 2026-07-29

## Context

PHP arrays are copy-on-write value types, which is close enough to immutability
that "just use PHP arrays" is the obvious first answer. It fails on three counts.
Copying a large array on every update is O(n) where a persistent structure is
effectively O(log32 n); PHP array keys are only `int|string`, so a vector, a
keyword or a struct cannot be a key; and `==` on nested arrays does not implement
the value equality a Lisp needs, while object identity is wrong in the other
direction.

The alternative to implementing them is to expose mutable structures and rely on
discipline. That trades away the property most of the language depends on: `swap!`
on an atom, structural sharing in a loop, and safely handing a collection to code
you do not control.

## Decision

`src/php/Lang/Collections/` implements real persistent data structures in PHP, and
they are the representation Phel literals compile to.

| Type | Implementation |
|---|---|
| Vector | `PersistentVector`, a 32-way trie |
| Map | `PersistentArrayMap` for small maps, promoted to `PersistentHashMap` (HAMT) |
| List | `PersistentList`, singly linked |
| Set | `PersistentHashSet`, over the hash map |
| Lazy seq | `LazySeq`, realised and cached per element |
| Struct | `AbstractPersistentStruct`, a fixed-key map subclassed by `defstruct` |

`Queue/`, `SortedMap/` and `SortedSet/` follow the same rules; the table lists the
types a compiled literal produces.

Two contracts hold it together:

- `Equalizer` decides value equality (`===` for scalars, structural for
  collections) and `Hasher` produces `int` hashes that **agree** with it. A
  disagreement silently loses map entries, which makes this the single most
  load-bearing invariant in `Lang/`.
- Every type implements `TypeInterface`, composing `MetaInterface`,
  `SourceLocationInterface`, `EqualsInterface` and `HashableInterface`, so
  metadata and source locations survive collection operations.

Transients (`transient`, mutate, `persistent!`) exist for bulk building and must
never escape the scope that created them. Reuse after `persistent!` throws for
every flavour reachable from Phel code, and a transient carries the metadata of
the collection it was opened from and hands it back, so the round trip preserves
meta the way Clojure's does.

PHP objects that are not Phel types fall back to `spl_object_hash`, so they
compare by identity.

## Consequences

`Lang/` is a leaf that everything depends on, so a change there breaks ten modules
at once. That is working as designed rather than a coupling problem, and it is why
`Lang/` carries the heaviest mutation-testing coverage in the project.

The performance profile is a promise the project only partly makes. The complexity
classes of the collections are documented and stable; constant factors are not,
and the spec says so explicitly.

The hierarchy has one shape that reads as duplication and is not. `merge()` has two
strategies, picked by which trait a class uses rather than by an `instanceof` in
the base: `AbstractPersistentMap::merge()` folds with `put()`, which works for
every implementation including those with no transient at all, since
`AbstractPersistentStruct::asTransient()` throws; `PersistentHashMap` and
`PersistentArrayMap` opt into `TransientMergeStrategyTrait` and bulk-build through
a transient. Collapsing them would break the struct. `PersistentMapMergeTest` pins
both.

`LazySeq` realisation is the other recurring surprise: a value is realised on first
access, so side effects in a lazy pipeline fire at consumption time, and code that
assumes an eager `map` is wrong in a way tests written against small inputs will
not show. This is also why `map` and `filter` are not lowered to native loops by
the emitter's specialisation pass, while an eager `reduce` is.

## Enforcement

- `tests/php/Unit/Lang/` covers the structures directly
- `mutation.yml` runs Infection over `Lang/` and the analyzer weekly against an
  MSI floor, which is the gate that catches a test asserting nothing
- `phpbench` guards the constant factors against the base revision (25% in CI,
  17% locally where the machine is quiet)

## Alternatives considered

- **Plain PHP arrays.** Rejected: O(n) updates, `int|string` keys only, and no
  usable value equality.
- **A C extension.** Rejected: Phel installs with Composer and runs wherever PHP
  runs. Requiring an extension would cost more users than the speed buys.
- **`ds` (the data structures extension) when available.** Same objection, plus
  two code paths to keep behaviourally identical.

## See also

- `src/php/Lang/CLAUDE.md`
- [Runtime internals](../internals/runtime.md)
- [Data structures](https://phel-lang.org/documentation/language/data-structures/)
  (user-facing)
