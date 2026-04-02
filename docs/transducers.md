# Transducers in Phel

## What are transducers?

Transducers are composable transformation pipelines that decouple the "what" (map, filter, take, etc.) from the "how" (building a vector, summing, writing to a file). They transform one reducing function into another, without creating intermediate collections at each step.

A regular pipeline like this creates a temporary sequence at every step:

```phel
;; Two intermediate lazy sequences created
(filter even? (map inc [1 2 3 4 5]))
```

With transducers, the transformations fuse into a single pass:

```phel
;; No intermediate collections
(sequence (comp (map inc) (filter even?)) [1 2 3 4 5])
; => [2 4 6]
```

## Basic usage

There are three ways to consume a transducer:

### `transduce` -- reduce with a transformation

Applies a transducer, then reduces with a combining function:

```phel
;; Sum all values after incrementing
(transduce (map inc) + [1 2 3])
; => 9   (i.e. 2 + 3 + 4)

;; With an explicit initial value
(transduce (filter even?) + 0 [1 2 3 4 5 6])
; => 12  (i.e. 0 + 2 + 4 + 6)
```

### `into` -- pour transformed elements into a collection

```phel
;; Apply a transducer and collect into a target collection
(into [] (map inc) [1 2 3])
; => [2 3 4]

(into [] (map |(assoc $ :active true)) [{:name "a"} {:name "b"}])
; => [{:name "a" :active true} {:name "b" :active true}]
```

### `sequence` -- get a vector of transformed results

A shorthand for `(into [] xf coll)`:

```phel
(sequence (filter even?) [1 2 3 4 5 6])
; => [2 4 6]
```

## Transducer-producing functions

Most sequence functions in Phel are dual-purpose: call them **with** a collection and they return a lazy sequence; call them **without** a collection and they return a transducer.

| Function | Transducer form | Description |
|---|---|---|
| `map` | `(map f)` | Apply `f` to each element |
| `filter` | `(filter pred)` | Keep elements where `(pred x)` is truthy |
| `remove` | `(remove pred)` | Keep elements where `(pred x)` is falsy |
| `take` | `(take n)` | Take first `n` elements, then stop |
| `drop` | `(drop n)` | Skip first `n` elements |
| `take-while` | `(take-while pred)` | Take while `(pred x)` is truthy, then stop |
| `drop-while` | `(drop-while pred)` | Skip while `(pred x)` is truthy |
| `take-nth` | `(take-nth n)` | Take every nth element |
| `keep` | `(keep f)` | Keep non-nil results of `(f x)` |
| `keep-indexed` | `(keep-indexed f)` | Keep non-nil results of `(f index x)` |
| `distinct` | `(distinct)` | Remove duplicates |
| `dedupe` | `(dedupe)` | Remove consecutive duplicates |
| `mapcat` | `(mapcat f)` | Map then concatenate (flatten one level) |
| `interpose` | `(interpose sep)` | Insert `sep` between elements |
| `cat` | `cat` | Concatenate nested collections (not dual-purpose; always a transducer) |

## Composing transducers

Use `comp` to build a pipeline. Transducers compose left-to-right (the leftmost runs first):

```phel
(def xf (comp
          (filter even?)     ;; 1. keep even numbers
          (map |(* $ $))     ;; 2. square them
          (take 3)))         ;; 3. stop after 3 results

(sequence xf (range 1 20))
; => [4 16 36]
```

This is the opposite of normal function composition, but matches the order you would write with threading:

```phel
;; Equivalent lazy-sequence version (creates intermediates):
(->> (range 1 20)
     (filter even?)
     (map |(* $ $))
     (take 3))
```

## Early termination

### `reduced`, `reduced?`, `unreduced`

A reducing function can signal "I'm done, stop iterating" by wrapping its return value in `reduced`:

```phel
;; Sum until the accumulator exceeds 10
(reduce
  (fn [acc x] (if (> acc 10) (reduced acc) (+ acc x)))
  0
  [1 2 3 4 5 6 7 8 9 10])
; => 15
```

- `(reduced x)` -- wraps `x` to signal early termination
- `(reduced? x)` -- returns true if `x` is a wrapped Reduced value
- `(unreduced x)` -- unwraps a Reduced value; returns `x` unchanged if not reduced

### How built-in transducers use early termination

`take` and `take-while` rely on `reduced` internally. When `take` has collected enough elements, it wraps the result in `reduced` so the outer `reduce`/`transduce` stops iterating immediately rather than walking the rest of the collection.

```phel
;; Stops processing after 2 elements, does not touch the rest
(transduce (take 2) conj [1 2 3 4 5])
; => [1 2]
```

## Stateful transducers

Some transducers need mutable state across steps (counters, sets of seen values, etc.). Phel provides volatile references for this:

- `(volatile! val)` -- create a mutable reference initialized to `val`
- `@vol` (deref) -- read the current value
- `(vreset! vol new-val)` -- set a new value, returns `new-val`
- `(vswap! vol f & args)` -- apply `f` to current value + args, set and return result

For example, `distinct` internally uses a volatile hash-set to track seen elements:

```phel
;; Simplified view of how distinct works internally:
;; (let [seen (volatile! (hash-set))]
;;   ... (if (contains? @seen input)
;;         result
;;         (do (vswap! seen conj input) (rf result input))))
```

## Custom transducers

A transducer is a function that takes a reducing function `rf` and returns a new reducing function. The returned function must handle three arities:

- **0-arity** (init): return `(rf)` -- delegate to the downstream init
- **1-arity** (completion): return `(rf result)` -- delegate completion, optionally flush state
- **2-arity** (step): the actual transformation logic

Due to a compiler limitation with nested multi-arity `fn`, use variadic `[& args]` with `case (count args)` dispatch instead.

### Using `xf-step` (recommended)

The private helper `xf-step` handles the 0/1 arity boilerplate. You only write the 2-arity step:

```phel
;; A transducer that doubles every element
(defn map-double []
  (fn [rf]
    (xf-step rf (fn [result input]
                  (rf result (* 2 input))))))

(sequence (map-double) [1 2 3])
; => [2 4 6]
```

### Full manual example

When you need custom completion logic (e.g., flushing buffered state), write all three arities:

```phel
;; A transducer that batches elements into groups of n
(defn batch [n]
  (fn [rf]
    (let [buf (volatile! [])]
      (fn [& args]
        (case (count args)
          0 (rf)
          1 (let [result (first args)
                  b @buf]
              ;; flush remaining items on completion
              (if (empty? b)
                (rf result)
                (rf (rf result b))))
          2 (let [result (first args)
                  input (second args)]
              (let [b (vswap! buf conj input)]
                (if (= (count b) n)
                  (do (vreset! buf [])
                      (rf result b))
                  result))))))))

(sequence (batch 3) [1 2 3 4 5 6 7])
; => [[1 2 3] [4 5 6] [7]]
```

### With early termination

If your transducer needs to stop processing, wrap the result in `reduced`:

```phel
;; Take until predicate returns true (inclusive)
(defn take-until [pred]
  (fn [rf]
    (xf-step rf (fn [result input]
                  (if (pred input)
                    (reduced (rf result input))
                    (rf result input))))))

(sequence (take-until |(> $ 3)) [1 2 3 4 5])
; => [1 2 3 4]
```

## Relationship to lazy sequences

Every dual-purpose function works in two modes:

```phel
;; With collection: returns a lazy sequence
(map inc [1 2 3])         ; => (2 3 4)
(filter even? [1 2 3 4])  ; => (2 4)

;; Without collection: returns a transducer
(map inc)                  ; => <transducer>
(filter even?)             ; => <transducer>
```

**When to use which:**

- **Lazy sequences** are fine for simple, linear pipelines. They compose naturally with `->>`.
- **Transducers** shine when you need to:
  - Avoid intermediate collections in a multi-step pipeline
  - Reuse the same transformation with different sources or destinations
  - Reduce into something other than a sequence (sums, maps, side effects)

```phel
;; Define once, reuse everywhere
(def xf (comp (filter even?) (map inc)))

;; Use with different consumers
(sequence xf [1 2 3 4 5 6])         ; => [3 5 7]
(transduce xf + [1 2 3 4 5 6])      ; => 15
(into #{} xf [1 2 3 4 5 6])         ; => #{3 5 7}
```
