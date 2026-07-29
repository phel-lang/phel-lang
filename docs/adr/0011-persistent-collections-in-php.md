# ADR 0011: Persistent collections implemented in PHP

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

PHP arrays are copy-on-write value types, so "just use PHP arrays" is the obvious
first answer. It fails three ways: copying a large array per update is O(n) where a
persistent structure is O(log32 n); keys are only `int|string`, so a vector,
keyword or struct cannot be one; and `==` on nested arrays is not value equality,
while object identity is wrong the other way.

Mutable structures plus discipline trades away what the language depends on:
`swap!`, structural sharing in a loop, handing a collection to code you do not
control.

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

`Queue/`, `SortedMap/`, `SortedSet/` follow the same rules; the table lists what a
compiled literal produces.

Two contracts hold it together:

- `Equalizer` decides value equality (`===` for scalars, structural for
  collections); `Hasher` produces `int` hashes that **agree** with it. Disagreement
  silently loses map entries, the most load-bearing invariant in `Lang/`.
- Every type implements `TypeInterface` (`MetaInterface`,
  `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`), so metadata
  and source locations survive collection operations.

Transients (`transient`, mutate, `persistent!`) are for bulk building and must not
escape their scope. Reuse after `persistent!` throws for every flavour reachable
from Phel. A transient carries the metadata of the collection it was opened from
and hands it back, so the round trip preserves meta like Clojure's.

Non-Phel PHP objects fall back to `spl_object_hash`, comparing by identity.

## Consequences

- `Lang/` is a leaf under everything, so a change there breaks ten modules at once.
  By design, and why it carries the heaviest mutation coverage.
- Complexity classes are documented and stable; constant factors are not.
- `merge()` looks collapsible and is not. Strategy is picked by trait, not by an
  `instanceof` in the base: `AbstractPersistentMap::merge()` folds with `put()` and
  works everywhere including implementations with no transient, since
  `AbstractPersistentStruct::asTransient()` throws; `PersistentHashMap` and
  `PersistentArrayMap` opt into `TransientMergeStrategyTrait`. Collapsing breaks the
  struct; `PersistentMapMergeTest` pins both.
- `LazySeq` realises on first access, so side effects in a lazy pipeline fire at
  consumption time and code assuming an eager `map` is wrong in ways small-input
  tests miss. Also why the emitter lowers an eager `reduce` to a native loop but
  never `map` or `filter`.

## Enforcement

- `tests/php/Unit/Lang/` covers the structures directly
- `mutation.yml` runs Infection over `Lang/` and the analyzer weekly against an MSI
  floor
- `phpbench` guards constant factors against the base revision (25% CI, 17% local)

## Alternatives considered

- **Plain PHP arrays.** O(n) updates, `int|string` keys, no usable value equality.
- **A C extension.** Phel installs with Composer and runs wherever PHP runs.
- **`ds` when available.** Same objection, plus two code paths to keep identical.

## See also

`src/php/Lang/CLAUDE.md` · [Runtime](../internals/runtime.md) ·
[Data structures](https://phel-lang.org/documentation/language/data-structures/)
