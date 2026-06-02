# Data Structures Manipulation Guide

Functions for Phel's immutable, persistent data structures (vectors, maps, sets, lists). Operations return new versions while sharing structure with the original: reads stay O(1) amortized, updates cost log32(n). Names follow Clojure conventions. See the [quick reference](#quick-reference).

## Strings as Sequences

Strings work with every sequence function and iterate by UTF-8 character:

```phel
(for [c :in "hello"] c)             ; => ["h" "e" "l" "l" "o"]
(map php/strtoupper "hello")        ; => ["H" "E" "L" "L" "O"]
(filter #(not= % "l") "hello")      ; => ["h" "e" "o"]
(take 3 "hello")                    ; => ["h" "e" "l"]
(drop 2 "hello")                    ; => ["l" "l" "o"]
(first "hello")                     ; => "h"
(next "hello")                      ; => ["e" "l" "l" "o"]   (rest behaves the same)
(take-while #(not= % "l") "hello")  ; => ["h" "e"]
(drop-while #(not= % "l") "hello")  ; => ["l" "l" "o"]
(reduce str "" "hello")             ; => "hello"
(distinct "hheelloo")               ; => ["h" "e" "l" "o"]
(reverse "hello")                   ; => ["o" "l" "l" "e" "h"]
(frequencies "hello")               ; => {"h" 1 "e" 1 "l" 2 "o" 1}
```

Convert a string to a character vector with `seq`, or `phel.string/chars`:

```phel
(seq "hello")                       ; => ["h" "e" "l" "l" "o"]
```

```phel
(ns app.example (:require phel.string :as str))
(str/chars "hello")                 ; => ["h" "e" "l" "l" "o"]
```

Multibyte UTF-8 is handled correctly:

```phel
(count "café")          ; => 4
(first "日本語")         ; => "日"
(frequencies "🎉🎉🎊")  ; => {"🎉" 2 "🎊" 1}
(map identity "🎉🎊🎈") ; => ["🎉" "🎊" "🎈"]
```

## Adding & Building

### `conj` : conjoin (universal add)

Adds elements; position depends on type. **Signature:** `([] [coll] [coll value] [coll value & more])`

```phel
(conj [1 2 3] 4)        ; => [1 2 3 4]      ; vectors append
(conj '(1 2 3) 4)       ; => (4 1 2 3)      ; lists prepend
(conj #{1 2 3} 4)       ; => #{1 2 3 4}     ; sets add
(conj {:a 1} [:b 2])    ; => {:a 1 :b 2}    ; maps take [k v]
(conj {:a 1} {:b 2 :c 3}) ; => {:a 1 :b 2 :c 3}  ; or merge a map
(conj nil 1)            ; => (1)            ; nil creates a list
(conj [1 2] 3 4 5)      ; => [1 2 3 4 5]    ; variadic
```

### `assoc` : associate key/value

Maps: add or update an entry. Vectors: update by index. **Signature:** `[ds key value]`

```phel
(assoc {:a 1} :b 2)       ; => {:a 1 :b 2}
(assoc {:a 1 :b 2} :a 3)  ; => {:a 3 :b 2}
(assoc [10 20 30] 1 99)   ; => [10 99 30]
```

See also `conj` for structured data, `assoc-in` for nested updates.

### `into` : bulk addition

Adds all elements from the source(s). **Signatures:** `[to from]`, `[to xform from]`

```phel
(into [1 2] [3 4 5])        ; => [1 2 3 4 5]
(into {} [[:a 1] [:b 2]])   ; => {:a 1 :b 2}
(into #{1 2} [2 3 4])       ; => #{1 2 3 4}
(into [] (map inc) [1 2 3]) ; => [2 3 4]   ; with a transducer
```

See [Transducers](transducers.md).

## Accessing

### `get` / `get-in`

`get` reads one key (maps) or index (vectors), returning `nil` or an optional default. `get-in` walks a path of keys/indices. **Signatures:** `[ds k & [opt]]`, `[ds ks & [opt]]`

```phel
(get {:a 1 :b 2} :a)              ; => 1
(get {:a 1 :b 2} :c "default")    ; => "default"
(get [10 20 30] 1)                ; => 20

(get-in {:a {:b {:c 1}}} [:a :b :c])         ; => 1
(get-in {:a [["b" "a"]]} [:a 0 1])           ; => "a"
(get-in {:a {:b 1}} [:a :x :y] "not-found")  ; => "not-found"
```

### `keys` / `vals`

Return a map's keys or values. **Signature:** `[coll]`

```phel
(keys {:a 1 :b 2 :c 3})  ; => (:a :b :c)
(vals {:a 1 :b 2 :c 3})  ; => (1 2 3)
```

`values` is a deprecated alias of `vals` (since v0.32.0) that still works.

### `contains?` : check for key

Returns `true` if the key/index exists. Checks **keys/indices, not values**. **Signature:** `[coll key]`

```phel
(contains? {:a 1 :b 2} :a)  ; => true
(contains? [10 20 30] 1)    ; => true  ; index 1 exists
(contains? [10 20 30] 3)    ; => false ; index 3 doesn't
```

### `find` : find first match

Returns the first item where the predicate is true, else `nil`. **Signature:** `[pred coll]`

```phel
(find #(> % 5) [1 3 7 2 9])  ; => 7
(find even? [1 3 5 7])       ; => nil
```

**Clojure note:** Clojure's `find` returns a `[key value]` entry on associative structures; Phel's `find` is a general search function.

## Modifying

### `update` / `update-in` : transform a value

Apply a function (plus extra args) to the value at a key/path. **Signatures:** `[ds k f & args]`, `[ds [k & ks] f & args]`

```phel
(update {:a 1} :a inc)                    ; => {:a 2}
(update {:a 1} :a + 10)                   ; => {:a 11}
(update [10 20 30] 1 * 2)                 ; => [10 40 30]
(update-in {:a {:b 1}} [:a :b] inc)       ; => {:a {:b 2}}
(update-in {:a {:b [1 2 3]}} [:a :b 0] * 10) ; => {:a {:b [10 2 3]}}
```

### `assoc-in` : nested association

Sets a nested value, creating intermediate maps as needed. **Signature:** `[ds [k & ks] v]`

```phel
(assoc-in {:a {}} [:a :b :c] 1)            ; => {:a {:b {:c 1}}}
(assoc-in {:a {:b [1 2 3]}} [:a :b 0] 99)  ; => {:a {:b [99 2 3]}}
```

### `dissoc` / `dissoc-in` : remove keys

Remove a key (`dissoc`) or a nested key (`dissoc-in` ⭐). **Signatures:** `[ds key]`, `[ds [k & ks]]`

```phel
(dissoc {:a 1 :b 2 :c 3} :b)               ; => {:a 1 :c 3}
(dissoc #{:a :b :c} :b)                    ; => #{:a :c}
(dissoc-in {:a {:b {:c 1 :d 2}}} [:a :b :c]) ; => {:a {:b {:d 2}}}
```

⭐ `dissoc-in` is Phel-specific (not in Clojure core).

## Combining Maps

### `merge` / `merge-with` / `deep-merge`

`merge` combines maps, later values win. `merge-with` resolves duplicates with a function. `deep-merge` (⭐) recurses into nested maps, sets, and vectors. **Signatures:** `[& maps]`, `[f & maps]`, `[& args]`

```phel
(merge {:a 1 :b 2} {:b 3 :c 4})            ; => {:a 1 :b 3 :c 4}
(merge {:a 1} {:b 2} {:c 3})               ; => {:a 1 :b 2 :c 3}

(merge-with + {:a 1 :b 2} {:b 3 :c 4})     ; => {:a 1 :b 5 :c 4}
(merge-with concat {:a [1]} {:a [2] :b [3]}) ; => {:a [1 2] :b [3]}

(deep-merge {:a {:b 1 :c 2}} {:a {:c 3 :d 4}}) ; => {:a {:b 1 :c 3 :d 4}}
(deep-merge {:a #{:b :c}} {:a #{:c :d}})       ; => {:a #{:b :c :d}}
(deep-merge {:a [1 2]} {:a [3]})               ; => {:a [1 2 3]}
```

⭐ `deep-merge` is Phel-specific; handy for config merging.

### `select-keys` / `zipmap` / `invert`

```phel
(select-keys {:a 1 :b 2 :c 3} [:a :c])  ; => {:a 1 :c 3}
(select-keys {:a 1 :b 2} [:a :x])       ; => {:a 1}     ; missing keys ignored

(zipmap [:a :b :c] [1 2 3])             ; => {:a 1 :b 2 :c 3}
(zipmap [:a :b :c] [1 2])               ; => {:a 1 :b 2} ; extras dropped

(invert {:a 1 :b 2})                    ; => {1 :a 2 :b} ; swap keys/values
```

**Signatures:** `[m ks]`, `[keys vals]`, `[map]`

## Analysis

### `frequencies` / `group-by`

`frequencies` counts occurrences (also accepts strings). `group-by` buckets elements by a function's result. **Signatures:** `[coll]`, `[f coll]`

```phel
(frequencies [:a :b :a :c :b :a])           ; => {:a 3 :b 2 :c 1}
(frequencies "hello")                       ; => {"h" 1 "e" 1 "l" 2 "o" 1}

(group-by count ["a" "bb" "ccc" "dd" "e"])  ; => {1 ["a" "e"] 2 ["bb" "dd"] 3 ["ccc"]}
(group-by even? [1 2 3 4 5 6])              ; => {false [1 3 5] true [2 4 6]}
```

## Nested Structures

`*-in` functions take a path (sequence of keys/indices) to navigate. Paths mix map keys and vector indices freely.

```phel
(get-in data [:user :profile :email])         ; read
(update-in data [:user :age] inc)              ; update
(assoc-in {} [:a :b :c] 42)                    ; => {:a {:b {:c 42}}}  (creates maps)
(dissoc-in data [:user :profile :temp-data])   ; remove
(get-in {:users [{:name "Alice"} {:name "Bob"}]} [:users 1 :name]) ; => "Bob"

(def data {:items [{:id 1 :tags #{:a :b}} {:id 2 :tags #{:c}}]})
(update-in data [:items 0 :tags] conj :c)
;; => {:items [{:id 1 :tags #{:a :b :c}} {:id 2 :tags #{:c}}]}
```

## Transient Collections

For hot paths with many sequential updates, use transients: efficient in-place mutation, then convert back to persistent. Mutate with the bang variants `conj!`, `assoc!`, `dissoc!`; finish with `persistent` (or its alias `persistent!`). Do not use a transient after converting it.

```phel
(def t (transient []))
(conj! t 1) (conj! t 2) (conj! t 3)
(persistent t)  ; => [1 2 3]

(defn build-map [^int n]
  (let [t (transient {})]
    (loop [i 0]
      (when (< i n)
        (assoc! t i (* i i))
        (recur (inc i))))
    (persistent! t)))
```

## Phel-Specific Extensions ⭐

Not in Clojure core.

```phel
(pairs {:a 1 :b 2})         ; => ([:a 1] [:b 2])  ; key/value pairs
(kvs {:a 1 :b 2 :c 3})      ; => [:a 1 :b 2 :c 3] ; flat alternating k/v
(find-index #(> % 5) [1 3 7 2 9]) ; => 2  ; index of first match (pred gets the item)
(find-index even? [1 3 4 6])      ; => 2
```

`kvs` is useful for APIs expecting key/value arguments.

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
| Get all values | `vals` | `(vals {:a 1 :b 2})` → `(1 2)` |
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

## Deprecated Functions

| Deprecated (v0.25.0) | Use Instead |
|---------------------|-------------|
| `push` | `conj` |
| `put` | `assoc` |
| `unset` | `dissoc` |
| `put-in` | `assoc-in` |
| `unset-in` | `dissoc-in` |

Signatures and behavior are identical; just replace the name.

See `docs/examples/05_data-structures.phel` for runnable code.
