# Data Structures Manipulation Guide

This guide covers all functions for manipulating Phel's immutable data structures: vectors, maps, sets, and lists.

## Table of Contents

- [Introduction](#introduction)
- [String Iteration](#string-iteration-)
- [Adding & Building Collections](#adding--building-collections)
- [Accessing Elements](#accessing-elements)
- [Modifying Collections](#modifying-collections)
- [Combining Maps](#combining-maps)
- [Analysis Functions](#analysis-functions)
- [Working with Nested Structures](#working-with-nested-structures)
- [Transient Collections](#transient-collections)
- [Phel-Specific Extensions](#phel-specific-extensions-)
- [Quick Reference](#quick-reference)
- [Deprecated Functions](#deprecated-functions)

## Introduction

Phel's data structures are **immutable** and **persistent**. Operations return new versions of the data structure while efficiently sharing structure with the original. This guide focuses on functions that manipulate these structures.

### Clojure Compatibility

Phel aims for strong compatibility with Clojure. Most functions work identically to their Clojure counterparts. Where differences exist, they're noted inline with **"Clojure note:"** annotations.

---

## String Iteration 🎉

**New in Phel 0.25.0:** Strings are now iterable sequences, just like in Clojure!

You can use any sequence function directly on strings:

```phel
# Iterate with for
(for [c :in "hello"] c)
# => ["h" "e" "l" "l" "o"]

# Count frequency of characters
(frequencies "hello")
# => {"h" 1 "e" 1 "l" 2 "o" 1}

# Map over characters
(map php/strtoupper "hello")
# => ("H" "E" "L" "L" "O")

# Filter characters
(filter |(not= $ "l") "hello")
# => ("h" "e" "o")

# Count characters
(count "café")
# => 4
```

### String Conversion Functions

**`seq`** - Convert string to vector of characters (explicit conversion):
```phel
(seq "hello")
# => ["h" "e" "l" "l" "o"]
```

**`phel\str/chars`** - Convenience function for string → character vector:
```phel
(use phel\str)
(chars "hello")
# => ["h" "e" "l" "l" "o"]
```

### Unicode Support

All string operations properly handle multibyte UTF-8 characters:

```phel
(count "café")      # => 4
(frequencies "🎉🎉🎊")  # => {"🎉" 2 "🎊" 1}
(seq "日本語")       # => ["日" "本" "語"]
```

**Clojure note:** Identical to Clojure's string sequence behavior!

---

## Adding & Building Collections

### `conj` - Conjoin (Universal Add)

The universal function for adding elements to any collection type. Behavior varies by collection:

```phel
# Vectors - appends to end
(conj [1 2 3] 4)
# => [1 2 3 4]

# Lists - prepends to beginning
(conj '(1 2 3) 4)
# => (4 1 2 3)

# Sets - adds element
(conj #{1 2 3} 4)
# => #{1 2 3 4}

# Maps - accepts [key value] vectors
(conj {:a 1} [:b 2])
# => {:a 1 :b 2}

# Maps - merges another map
(conj {:a 1} {:b 2 :c 3})
# => {:a 1 :b 2 :c 3}

# Nil - creates a list
(conj nil 1)
# => (1)

# Variadic - add multiple elements
(conj [1 2] 3 4 5)
# => [1 2 3 4 5]
```

**Signature:** `([] [coll] [coll value] [coll value & more])`

**Clojure note:** Identical behavior to Clojure's `conj`.

---

### `assoc` - Associate Key-Value Pairs

Associates a value with a key in a collection. For maps, adds/updates key-value pairs. For vectors, updates by index.

```phel
# Maps - add or update key
(assoc {:a 1} :b 2)
# => {:a 1 :b 2}

(assoc {:a 1 :b 2} :a 3)
# => {:a 3 :b 2}

# Vectors - update by index
(assoc [10 20 30] 1 99)
# => [10 99 30]
```

**Signature:** `[ds key value]`

**Clojure note:** Identical behavior to Clojure's `assoc`.

**See also:** For maps with structured data, consider `conj`. For nested updates, use `assoc-in`.

---

### `into` - Bulk Addition

Returns a collection with all elements from one or more source collections added.

```phel
(into [1 2] [3 4 5])
# => [1 2 3 4 5]

(into [] '(1 2 3 4))
# => [1 2 3 4]

(into {} [[:a 1] [:b 2]])
# => {:a 1 :b 2}

(into #{1 2} [2 3 4])
# => #{1 2 3 4}
```

**Signature:** `[to & from]`

**Clojure note:** Phel's `into` doesn't support transducers yet. Use `(into to from)` only.

---

## Accessing Elements

### `get` - Safe Access

Gets the value at a key in a collection. Returns `nil` or an optional default if not found.

```phel
(get {:a 1 :b 2} :a)
# => 1

(get {:a 1 :b 2} :c)
# => nil

(get {:a 1 :b 2} :c "default")
# => "default"

# Works with vectors (by index)
(get [10 20 30] 1)
# => 20
```

**Signature:** `[ds k & [opt]]`

**Clojure note:** Identical behavior to Clojure's `get`.

---

### `get-in` - Nested Access

Accesses a value in a nested data structure via a sequence of keys.

```phel
(get-in {:a {:b {:c 1}}} [:a :b :c])
# => 1

(get-in {:a [["b" "a"]]} [:a 0 1])
# => "a"

# With default value
(get-in {:a {:b 1}} [:a :x :y] "not-found")
# => "not-found"
```

**Signature:** `[ds ks & [opt]]`

**Clojure note:** Identical behavior to Clojure's `get-in`.

---

### `keys` - Extract Keys

Returns a sequence of all keys in a map.

```phel
(keys {:a 1 :b 2 :c 3})
# => (:a :b :c)
```

**Signature:** `[coll]`

**Clojure note:** Identical behavior to Clojure's `keys`.

---

### `values` - Extract Values

Returns a sequence of all values in a map.

```phel
(values {:a 1 :b 2 :c 3})
# => (1 2 3)
```

**Signature:** `[coll]`

**Clojure note:** Clojure uses `vals` instead of `values`. This is a naming difference only.

---

### `contains?` - Check for Key

Returns `true` if a key exists in the collection. **Important:** Checks for keys/indices, not values.

```phel
(contains? {:a 1 :b 2} :a)
# => true

(contains? [10 20 30] 1)
# => true (index 1 exists)

(contains? [10 20 30] 3)
# => false (index 3 doesn't exist)
```

**Signature:** `[coll key]`

**Clojure note:** Identical behavior to Clojure's `contains?`.

---

### `find` - Find First Match

Returns the first item in a collection where the predicate returns true.

```phel
(find |(> $ 5) [1 3 7 2 9])
# => 7

(find even? [1 3 5 7])
# => nil
```

**Signature:** `[pred coll]`

**Clojure note:** Different from Clojure! Clojure's `find` returns a map entry `[key value]` for associative structures. Phel's `find` is a general search function.

---

## Modifying Collections

### `update` - Transform a Value

Updates a value in a data structure by applying a function to the current value.

```phel
(update {:a 1} :a inc)
# => {:a 2}

(update {:a 1} :a + 10)
# => {:a 11}

(update [10 20 30] 1 * 2)
# => [10 40 30]
```

**Signature:** `[ds k f & args]`

**Clojure note:** Identical behavior to Clojure's `update`.

---

### `update-in` - Nested Transform

Updates a value in a nested data structure by applying a function.

```phel
(update-in {:a {:b 1}} [:a :b] inc)
# => {:a {:b 2}}

(update-in {:a {:b [1 2 3]}} [:a :b 0] * 10)
# => {:a {:b [10 2 3]}}
```

**Signature:** `[ds [k & ks] f & args]`

**Clojure note:** Identical behavior to Clojure's `update-in`.

---

### `assoc-in` - Nested Association

Associates a value in a nested data structure. Creates intermediate structures as needed.

```phel
(assoc-in {:a {}} [:a :b :c] 1)
# => {:a {:b {:c 1}}}

(assoc-in {:a {:b [1 2 3]}} [:a :b 0] 99)
# => {:a {:b [99 2 3]}}
```

**Signature:** `[ds [k & ks] v]`

**Clojure note:** Identical behavior to Clojure's `assoc-in`.

---

### `dissoc` - Dissociate Keys

Removes a key from a data structure.

```phel
(dissoc {:a 1 :b 2 :c 3} :b)
# => {:a 1 :c 3}

(dissoc #{:a :b :c} :b)
# => #{:a :c}
```

**Signature:** `[ds key]`

**Clojure note:** Identical behavior to Clojure's `dissoc`.

---

### `dissoc-in` - Nested Dissociation ⭐

Removes a key from a nested data structure.

```phel
(dissoc-in {:a {:b {:c 1 :d 2}}} [:a :b :c])
# => {:a {:b {:d 2}}}
```

**Signature:** `[ds [k & ks]]`

**⭐ Phel-specific:** This function is not in Clojure core. It's a convenient extension for nested dissociation.

---

## Combining Maps

### `merge` - Merge Maps

Merges multiple maps into one. Later values override earlier ones for duplicate keys.

```phel
(merge {:a 1 :b 2} {:b 3 :c 4})
# => {:a 1 :b 3 :c 4}

(merge {:a 1} {:b 2} {:c 3})
# => {:a 1 :b 2 :c 3}
```

**Signature:** `[& maps]`

**Clojure note:** Identical behavior to Clojure's `merge`.

---

### `merge-with` - Merge with Function

Merges maps, using a function to resolve duplicate keys.

```phel
(merge-with + {:a 1 :b 2} {:b 3 :c 4})
# => {:a 1 :b 5 :c 4}

(merge-with concat {:a [1]} {:a [2] :b [3]})
# => {:a [1 2] :b [3]}
```

**Signature:** `[f & maps]`

**Clojure note:** Identical behavior to Clojure's `merge-with`.

---

### `deep-merge` - Recursive Merge ⭐

Recursively merges nested data structures (maps, sets, vectors).

```phel
(deep-merge {:a {:b 1 :c 2}} {:a {:c 3 :d 4}})
# => {:a {:b 1 :c 3 :d 4}}

(deep-merge {:a #{:b :c}} {:a #{:c :d}})
# => {:a #{:b :c :d}}

(deep-merge {:a [1 2]} {:a [3]})
# => {:a [1 2 3]}
```

**Signature:** `[& args]`

**⭐ Phel-specific:** This recursive merge is not in Clojure core. It's particularly useful for configuration merging.

---

### `select-keys` - Filter by Keys

Returns a new map with only the specified keys.

```phel
(select-keys {:a 1 :b 2 :c 3} [:a :c])
# => {:a 1 :c 3}

(select-keys {:a 1 :b 2} [:a :x])
# => {:a 1}
```

**Signature:** `[m ks]`

**Clojure note:** Identical behavior to Clojure's `select-keys`.

---

### `zipmap` - Create Map from Sequences

Creates a map by pairing up keys and values from two sequences.

```phel
(zipmap [:a :b :c] [1 2 3])
# => {:a 1 :b 2 :c 3}

# Drops extra keys or values
(zipmap [:a :b :c] [1 2])
# => {:a 1 :b 2}
```

**Signature:** `[keys vals]`

**Clojure note:** Identical behavior to Clojure's `zipmap`.

---

### `invert` - Swap Keys and Values

Returns a new map where keys and values are swapped.

```phel
(invert {:a 1 :b 2})
# => {1 :a 2 :b}
```

**Signature:** `[map]`

**Clojure note:** In Clojure, this is `clojure.set/map-invert`, not in core. Phel includes it in core.

---

## Analysis Functions

### `frequencies` - Count Occurrences

Returns a map of items to the number of times they appear.

```phel
(frequencies [:a :b :a :c :b :a])
# => {:a 3 :b 2 :c 1}

(frequencies "hello")
# => {"h" 1 "e" 1 "l" 2 "o" 1}
```

**Signature:** `[coll]`

**Clojure note:** Identical behavior to Clojure's `frequencies`. Phel supports strings as iterable sequences!

---

### `group-by` - Organize by Function

Groups elements by the result of applying a function to each element.

```phel
(group-by count ["a" "bb" "ccc" "dd" "e"])
# => {1 ["a" "e"] 2 ["bb" "dd"] 3 ["ccc"]}

(group-by even? [1 2 3 4 5 6])
# => {false [1 3 5] true [2 4 6]}
```

**Signature:** `[f coll]`

**Clojure note:** Identical behavior to Clojure's `group-by`.

---

## Working with Nested Structures

Phel provides powerful functions for working with deeply nested data structures. All `*-in` functions accept a path (sequence of keys) to navigate the structure.

### Common Patterns

```phel
# Read nested value
(get-in data [:user :profile :email])

# Update nested value
(update-in data [:user :age] inc)

# Set nested value (creates intermediate maps)
(assoc-in {} [:a :b :c] 42)
# => {:a {:b {:c 42}}}

# Remove nested key
(dissoc-in data [:user :profile :temp-data])

# Mixed indices and keys
(get-in {:users [{:name "Alice"} {:name "Bob"}]} [:users 1 :name])
# => "Bob"
```

### Path Navigation

Paths work with any combination of map keys and vector indices:

```phel
(def data {:items [{:id 1 :tags #{:a :b}}
                   {:id 2 :tags #{:c}}]})

(update-in data [:items 0 :tags] conj :c)
# => {:items [{:id 1 :tags #{:a :b :c}} {:id 2 :tags #{:c}}]}
```

---

## Transient Collections

For performance-critical code with many sequential updates, use **transient collections**. They allow efficient mutable operations, then convert back to persistent structures.

### Workflow

```phel
# 1. Create transient version
(def t (transient []))

# 2. Perform mutations (use same functions: conj, assoc, dissoc)
(conj t 1)
(conj t 2)
(conj t 3)

# 3. Convert back to persistent
(def result (persistent t))
# => [1 2 3]
```

### Example: Building a large map

```phel
(defn build-map [n]
  (let [t (transient {})]
    (loop [i 0]
      (when (< i n)
        (assoc t i (* i i))
        (recur (inc i))))
    (persistent t)))
```

**Clojure note:** Phel uses `persistent` while Clojure uses `persistent!` (with exclamation mark).

**Important:** After calling `persistent`, don't use the transient version anymore.

---

## Phel-Specific Extensions ⭐

Phel includes several functions not found in Clojure core that provide additional convenience.

### `dissoc-in` - Nested Dissociation

Remove keys from nested structures without manual path traversal.

```phel
(dissoc-in {:a {:b {:c 1 :d 2} :e 3}} [:a :b :c])
# => {:a {:b {:d 2} :e 3}}
```

This is more convenient than manually navigating and updating each level.

---

### `deep-merge` - Recursive Merge

Intelligently merges nested structures of the same type.

```phel
# Nested maps
(deep-merge
  {:config {:db {:host "localhost" :port 5432}
            :cache {:ttl 300}}}
  {:config {:db {:port 3306}
            :logging {:level "debug"}}})
# => {:config {:db {:host "localhost" :port 3306}
#              :cache {:ttl 300}
#              :logging {:level "debug"}}}

# Nested sets
(deep-merge {:tags #{:a :b}} {:tags #{:b :c}})
# => {:tags #{:a :b :c}}
```

Perfect for configuration merging and defaults.

---

### `pairs` - Get Key-Value Pairs

Returns key-value pairs from an associative data structure.

```phel
(pairs {:a 1 :b 2})
# => ([:a 1] [:b 2])
```

---

### `kvs` - Flatten Key-Value Pairs

Returns a flat vector of alternating keys and values.

```phel
(kvs {:a 1 :b 2 :c 3})
# => [:a 1 :b 2 :c 3]
```

Useful for APIs that expect key-value arguments.

---

### `find-index` - Find with Index

Returns the index of the first item where the predicate (called with index and item) returns true.

```phel
(find-index |(> $2 5) [1 3 7 2 9])
# => 2  (index of 7)

(find-index |(even? $1) [:a :b :c :d])
# => 1  (first even index)
```

The predicate receives two arguments: `$1` (index) and `$2` (item).

---

## Quick Reference

| Operation | Function | Example |
|-----------|----------|---------|
| **Adding** |
| Add to collection | `conj` | `(conj [1 2] 3)` → `[1 2 3]` |
| Set key-value | `assoc` | `(assoc {:a 1} :b 2)` → `{:a 1 :b 2}` |
| Bulk add | `into` | `(into [1] [2 3])` → `[1 2 3]` |
| **Accessing** |
| Get value | `get` | `(get {:a 1} :a)` → `1` |
| Get nested | `get-in` | `(get-in {:a {:b 1}} [:a :b])` → `1` |
| Check key exists | `contains?` | `(contains? {:a 1} :a)` → `true` |
| Get all keys | `keys` | `(keys {:a 1 :b 2})` → `(:a :b)` |
| Get all values | `values` | `(values {:a 1 :b 2})` → `(1 2)` |
| **Modifying** |
| Transform value | `update` | `(update {:a 1} :a inc)` → `{:a 2}` |
| Transform nested | `update-in` | `(update-in {:a {:b 1}} [:a :b] inc)` → `{:a {:b 2}}` |
| Set nested | `assoc-in` | `(assoc-in {} [:a :b] 1)` → `{:a {:b 1}}` |
| Remove key | `dissoc` | `(dissoc {:a 1 :b 2} :a)` → `{:b 2}` |
| Remove nested ⭐ | `dissoc-in` | `(dissoc-in {:a {:b 1}} [:a :b])` → `{:a {}}` |
| **Combining** |
| Merge maps | `merge` | `(merge {:a 1} {:b 2})` → `{:a 1 :b 2}` |
| Merge with fn | `merge-with` | `(merge-with + {:a 1} {:a 2})` → `{:a 3}` |
| Deep merge ⭐ | `deep-merge` | `(deep-merge {:a {:b 1}} {:a {:c 2}})` → `{:a {:b 1 :c 2}}` |
| Filter keys | `select-keys` | `(select-keys {:a 1 :b 2} [:a])` → `{:a 1}` |
| Zip to map | `zipmap` | `(zipmap [:a :b] [1 2])` → `{:a 1 :b 2}` |
| Swap keys/vals | `invert` | `(invert {:a 1})` → `{1 :a}` |
| **Analysis** |
| Count items | `frequencies` | `(frequencies [1 1 2])` → `{1 2 2 1}` |
| Group by fn | `group-by` | `(group-by even? [1 2 3])` → `{false [1 3] true [2]}` |

⭐ = Phel-specific extension

---

## Deprecated Functions

These functions have been deprecated in favor of Clojure-compatible alternatives:

| Deprecated (v0.25.0) | Use Instead | Reason |
|---------------------|-------------|---------|
| `push` | `conj` | Align with Clojure naming |
| `put` | `assoc` | Align with Clojure naming |
| `unset` | `dissoc` | Align with Clojure naming |
| `put-in` | `assoc-in` | Align with Clojure naming |
| `unset-in` | `dissoc-in` | Align with Clojure naming |

**Migration:** Simply replace the old function name with the new one. The function signatures and behavior are identical.

```phel
# Old (deprecated)
(push [1 2] 3)
(put {:a 1} :b 2)

# New (recommended)
(conj [1 2] 3)
(assoc {:a 1} :b 2)
```

---

## Summary

Phel provides a comprehensive set of functions for manipulating immutable data structures with strong Clojure compatibility. Key takeaways:

- Use **`conj`** as the universal "add" function
- Use **`assoc`** for explicit key-value pairs
- Use **`*-in` functions** for nested operations
- Leverage **Phel-specific extensions** like `deep-merge` and `dissoc-in` for enhanced productivity
- Use **transient collections** for performance-critical batch updates
- Migrate from **deprecated functions** to Clojure-compatible names

For more examples, see `docs/examples/05_data-structures.phel`.
