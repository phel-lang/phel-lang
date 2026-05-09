# Transducers in Phel

## What are transducers?

Transducers are composable pipelines that decouple the transformation (map, filter, take) from the consumer (build a vector, sum, write to a file). They turn one reducing function into another without intermediate collections.

A normal pipeline allocates at every step:

```phel
;; Two intermediate lazy sequences created
(filter even? (map inc [1 2 3 4 5]))
```

Transducers fuse the steps into a single pass:

```phel
;; No intermediate collections
(sequence (comp (map inc) (filter even?)) [1 2 3 4 5])
; => [2 4 6]
```

## Basic usage

Three ways to consume a transducer:

### `transduce` -- reduce with a transformation

Apply a transducer, then reduce:

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
(into [] (map inc) [1 2 3])
; => [2 3 4]

(into [] (map #(assoc % :active true)) [{:name "a"} {:name "b"}])
; => [{:name "a" :active true} {:name "b" :active true}]
```

### `sequence` -- vector of transformed results

Shorthand for `(into [] xf coll)`:

```phel
(sequence (filter even?) [1 2 3 4 5 6])
; => [2 4 6]
```

## Transducer-producing functions

Most sequence functions are dual-purpose. Called **with** a collection they return a lazy sequence; called **without** they return a transducer.

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

`comp` builds a pipeline. Transducers compose left-to-right; the leftmost runs first:

```phel
(def xf (comp
          (filter even?)     ;; 1. keep even numbers
          (map #(* % %))     ;; 2. square them
          (take 3)))         ;; 3. stop after 3 results

(sequence xf (range 1 20))
; => [4 16 36]
```

Opposite of normal function composition, matching the order of `->>`:

```phel
;; Equivalent lazy-sequence version (creates intermediates):
(->> (range 1 20)
     (filter even?)
     (map #(* % %))
     (take 3))
```

## Early termination

### `reduced`, `reduced?`, `unreduced`

A reducing function signals "stop" by wrapping its return value in `reduced`:

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

`take` and `take-while` use `reduced` internally. Once `take` has enough elements, it wraps the result so the outer `reduce`/`transduce` stops rather than walking the rest:

```phel
;; Stops processing after 2 elements, does not touch the rest
(transduce (take 2) conj [1 2 3 4 5])
; => [1 2]
```

## Stateful transducers

Some transducers need mutable state across steps (counters, seen-sets). Phel provides volatile references:

- `(volatile! val)` -- create a mutable reference initialized to `val`
- `@vol` (deref) -- read the current value
- `(vreset! vol new-val)` -- set a new value, returns `new-val`
- `(vswap! vol f & args)` -- apply `f` to current value + args, set and return result

`distinct` uses a volatile hash-set to track seen elements:

```phel
;; Simplified view of how distinct works internally:
;; (let [seen (volatile! (hash-set))]
;;   ... (if (contains? @seen input)
;;         result
;;         (do (vswap! seen conj input) (rf result input))))
```

## Custom transducers

A transducer takes a reducing function `rf` and returns a new one. The returned function handles three arities:

- **0** (init): return `(rf)`, delegate downstream init
- **1** (completion): return `(rf result)`, optionally flush state
- **2** (step): the transformation logic

Nested multi-arity `fn` is not supported, so use variadic `[& args]` with `case (count args)` dispatch.

### Using `xf-step` (recommended)

`xf-step` handles 0/1 arity boilerplate. Write only the 2-arity step:

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

For custom completion logic (e.g. flushing buffered state), write all three arities:

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

Wrap the result in `reduced` to stop:

```phel
;; Take until predicate returns true (inclusive)
(defn take-until [pred]
  (fn [rf]
    (xf-step rf (fn [result input]
                  (if (pred input)
                    (reduced (rf result input))
                    (rf result input))))))

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

- **Lazy sequences**: simple linear pipelines. Compose with `->>`.
- **Transducers**: avoid intermediates in multi-step pipelines, reuse one transformation across sources/destinations, or reduce into something that isn't a sequence (sums, maps, side effects).

```phel
;; Define once, reuse everywhere
(def xf (comp (filter even?) (map inc)))

;; Use with different consumers
(sequence xf [1 2 3 4 5 6])         ; => [3 5 7]
(transduce xf + [1 2 3 4 5 6])      ; => 15
(into #{} xf [1 2 3 4 5 6])         ; => #{3 5 7}
```
