# Transducers in Phel

Transducers are composable pipelines that decouple the transformation (map, filter, take) from the consumer (build a vector, sum, write to a file). They turn one reducing function into another, with no intermediate collections.

A normal pipeline allocates at every step; transducers fuse the steps into a single pass:

```phel
;; Two intermediate lazy sequences
(filter even? (map inc [1 2 3 4 5]))

;; No intermediate collections
(sequence (comp (map inc) (filter even?)) [1 2 3 4 5])
; => [2 4 6]
```

## Basic usage

Three ways to consume a transducer:

```phel
;; transduce - apply a transducer, then reduce
(transduce (map inc) + [1 2 3])             ; => 9   (2 + 3 + 4)
(transduce (filter even?) + 0 [1 2 3 4 5 6]) ; => 12  (0 + 2 + 4 + 6, explicit init)

;; into - pour transformed elements into a collection
(into [] (map inc) [1 2 3])                 ; => [2 3 4]
(into [] (map #(assoc % :active true)) [{:name "a"} {:name "b"}])
; => [{:name "a" :active true} {:name "b" :active true}]

;; sequence - vector of transformed results; shorthand for (into [] xf coll)
(sequence (filter even?) [1 2 3 4 5 6])     ; => [2 4 6]
```

## Transducer-producing functions

Most sequence functions are dual-purpose: called **with** a collection they return a lazy sequence; called **without** one they return a transducer.

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

`comp` builds a pipeline. Transducers compose left-to-right (leftmost runs first), the opposite of normal function composition and matching the order of `->>`:

```phel
(def xf (comp
          (filter even?)     ;; 1. keep even numbers
          (map #(* % %))     ;; 2. square them
          (take 3)))         ;; 3. stop after 3 results

(sequence xf (range 1 20))
; => [4 16 36]

;; Equivalent lazy-sequence version (creates intermediates):
(->> (range 1 20)
     (filter even?)
     (map #(* % %))
     (take 3))
```

## Early termination

A reducing function signals "stop" by wrapping its return value in `reduced`:

```phel
;; Sum until the accumulator exceeds 10
(reduce
  (fn [acc x] (if (> acc 10) (reduced acc) (+ acc x)))
  0
  [1 2 3 4 5 6 7 8 9 10])
; => 15
```

- `(reduced x)` - wraps `x` to signal early termination
- `(reduced? x)` - true if `x` is a wrapped Reduced value
- `(unreduced x)` - unwraps a Reduced value; returns `x` unchanged if not reduced

`take` and `take-while` use `reduced` internally, so the outer `reduce`/`transduce` stops rather than walking the rest:

```phel
(transduce (take 2) conj [1 2 3 4 5])  ; => [1 2]  ; does not touch the rest
```

## Stateful transducers

Some transducers need mutable state across steps (counters, seen-sets). Phel provides volatile references:

- `(volatile! val)` - create a mutable reference initialized to `val`
- `@vol` (deref) - read the current value
- `(vreset! vol new-val)` - set a new value, returns `new-val`
- `(vswap! vol f & args)` - apply `f` to current value + args, set and return result

`distinct`, for example, uses a volatile hash-set to track seen elements:

```phel
;; Simplified view of how distinct works internally:
;; (let [seen (volatile! (hash-set))]
;;   ... (if (contains? @seen input)
;;         result
;;         (do (vswap! seen conj input) (rf result input))))
```

## Custom transducers

A transducer takes a reducing function `rf` and returns a new one handling three arities:

- **0** (init): return `(rf)`, delegate downstream init
- **1** (completion): return `(rf result)`, optionally flush state
- **2** (step): the transformation logic

Dispatch on arity with a variadic `[& args]` plus `case (count args)`, the shape Phel's own core transducers use internally. It works correctly when the returned fn closes over `rf` or other state. (A multi-arity `fn` with `([] ...) ([result] ...) ([result input] ...)` clauses reads cleaner but does not currently compile for transducers, so prefer the variadic form.)

```phel
;; A transducer that doubles every element
(defn map-double []
  (fn [rf]
    (fn [& args]
      (case (count args)
        0 (rf)
        1 (rf (first args))
        2 (rf (first args) (* 2 (second args)))))))

(sequence (map-double) [1 2 3])
; => [2 4 6]
```

### Custom completion logic

Override the 1-arity branch to flush buffered state:

```phel
;; Batch elements into groups of n
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

Wrap the step result in `reduced` to stop:

```phel
;; Take until predicate returns true (inclusive)
(defn take-until [pred]
  (fn [rf]
    (fn [& args]
      (case (count args)
        0 (rf)
        1 (rf (first args))
        2 (let [result (first args)
                input (second args)]
            (if (pred input)
              (reduced (rf result input))
              (rf result input)))))))

(sequence (take-until #(> % 3)) [1 2 3 4 5])
; => [1 2 3 4]
```

## Relationship to lazy sequences

Every dual-purpose function has two modes:

```phel
;; With collection: returns a lazy sequence
(map inc [1 2 3])         ; => (2 3 4)
(filter even? [1 2 3 4])  ; => (2 4)

;; Without collection: returns a transducer
(map inc)                  ; => <transducer>
(filter even?)             ; => <transducer>
```

**When to use which:**

- **Lazy sequences**: simple linear pipelines; compose with `->>`.
- **Transducers**: avoid intermediates in multi-step pipelines, reuse one transformation across sources/destinations, or reduce into something that isn't a sequence (sums, maps, side effects).

```phel
;; Define once, reuse with different consumers
(def xf (comp (filter even?) (map inc)))

(sequence xf [1 2 3 4 5 6])         ; => [3 5 7]
(transduce xf + [1 2 3 4 5 6])      ; => 15
(into #{} xf [1 2 3 4 5 6])         ; => #{3 5 7}
```

## See also

- [Lazy Sequences](lazy-sequences.md)
- [Data Structures](data-structures-guide.md) (`into`, `reduce`)
- [Common Patterns](patterns.md)
