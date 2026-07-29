# ADR 0011: Persistent collections implemented in PHP

- **Status**: Accepted (recorded retroactively; predates this record)
- **Date**: 2026-07-29

## Context

PHP arrays are copy-on-write value types, so "just use PHP arrays" is the obvious
first answer. It fails three ways: copying a large array per update is O(n) where
a persistent structure is O(log32 n); array keys are only `int|string`, so a
vector, keyword or struct cannot be a key; and `==` on nested arrays is not the
value equality a Lisp needs, while object identity is wrong in the other
direction.

The other option, mutable structures plus discipline, trades away the property
most of the language depends on: `swap!`, structural sharing in a loop, and handing
a collection to code you do not control.

## Decision

`src/php/Lang/Collections/` implements persistent structures in PHP, and they are
what Phel literals compile to.

| Type | Implementation |
|---|---|
| Vector | `PersistentVector`, 32-way trie |
| Map | `PersistentArrayMap` (small), promoted to `PersistentHashMap` (HAMT) |
| List | `PersistentList`, singly linked |
| Set | `PersistentHashSet`, over the hash map |
| Lazy seq | `LazySeq`, realised and cached per element |
| Struct | `AbstractPersistentStruct`, fixed-key map subclassed by `defstruct` |

`Queue/`, `SortedMap/` and `SortedSet/` follow the same rules; the table lists what
a compiled literal produces.

Two contracts hold it together:

- `Equalizer` decides value equality (`===` for scalars, structural for
  collections); `Hasher` produces `int` hashes that **agree** with it. Disagreement
  silently loses map entries, making this the most load-bearing invariant in
  `Lang/`.
- Every type implements `TypeInterface` (`MetaInterface`,
  `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`), so metadata
  and source locations survive collection operations.

Transients (`transient`, mutate, `persistent!`) exist for bulk building and must
not escape their scope. Reuse after `persistent!` throws for every flavour
reachable from Phel. A transient carries the metadata of the collection it was
opened from and hands it back, so the round trip preserves meta like Clojure's.

Non-Phel PHP objects fall back to `spl_object_hash`, so they compare by identity.

## Consequences

`Lang/` is a leaf under everything, so a change there breaks ten modules at once.
That is by design, and why `Lang/` carries the heaviest mutation coverage.

Complexity classes are documented and stable; constant factors are not, and the
spec says so.

One shape reads as duplication and is not. `merge()` has two strategies, chosen by
trait rather than an `instanceof` in the base: `AbstractPersistentMap::merge()`
folds with `put()` and works everywhere, including implementations with no
transient at all, since `AbstractPersistentStruct::asTransient()` throws;
`PersistentHashMap` and `PersistentArrayMap` opt into `TransientMergeStrategyTrait`
and bulk-build through a transient. Collapsing them breaks the struct.
`PersistentMapMergeTest` pins both.

`LazySeq` realises on first access, so side effects in a lazy pipeline fire at
consumption time and code assuming an eager `map` is wrong in ways small-input
tests miss. It is also why the emitter's specialisation pass lowers an eager
`reduce` to a native loop but never `map` or `filter`.

## Enforcement

- `tests/php/Unit/Lang/` covers the structures directly
- `mutation.yml` runs Infection over `Lang/` and the analyzer weekly against an MSI
  floor
- `phpbench` guards constant factors against the base revision (25% in CI, 17%
  locally)

## Alternatives considered

- **Plain PHP arrays.** O(n) updates, `int|string` keys, no usable value equality.
- **A C extension.** Phel installs with Composer and runs wherever PHP runs.
- **`ds` when available.** Same objection, plus two code paths to keep identical.

## See also

- `src/php/Lang/CLAUDE.md`, [Runtime](../internals/runtime.md)
- [Data structures](https://phel-lang.org/documentation/language/data-structures/)
