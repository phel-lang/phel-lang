# Lazy Sequences in Phel

Work with potentially infinite collections by deferring computation until values are needed.

## Overview

Two constructs:
- **`lazy-seq`**: creates a lazy sequence from an expression
- **`lazy-cat`**: lazily concatenates multiple collections

## The `lazy-seq` Macro

`lazy-seq` takes a body returning a sequence or nil and yields a LazySeq that invokes the body on first access, caching the result.

### Basic Usage

```phel
;; Create a lazy sequence that computes on demand
(def my-lazy-seq
  (lazy-seq
    (println "Computing...")
    [1 2 3 4 5]))

;; Nothing printed yet
(first my-lazy-seq)  ; Prints "Computing..." and returns 1
(first my-lazy-seq)  ; Returns 1 (cached, no printing)
```

### Infinite Sequences with `lazy-seq`

Combine `lazy-seq` with recursion to build infinite sequences:

```phel
;; Generate infinite sequence of integers starting from n
(defn ints-from [n]
  (lazy-seq
    (cons n (ints-from (inc n)))))

(take 5 (ints-from 0))  ; => [0 1 2 3 4]
(take 3 (ints-from 10)) ; => [10 11 12]
```

### How It Works

`lazy-seq`:
1. Creates a thunk (zero-arg function) that evaluates the body
2. Returns a LazySeq holding the thunk
3. On first access (`first`, `rest`, `take`, etc.), evaluates the thunk
4. Caches the result

## The `lazy-cat` Macro

Convenient syntax for concatenating collections.

**Important:** `lazy-cat` expands to `concat` and evaluates all arguments eagerly.
Fine for combining finite or already-realized lazy sequences, but
**NOT suitable for recursive infinite sequences**.

### Basic Concatenation

```phel
(lazy-cat [1 2] [3 4] [5 6])
;; => (1 2 3 4 5 6)

(lazy-cat (range 3) (range 3 6))
;; => (0 1 2 3 4 5)
```

### With Finite Lazy Sequences

Works with lazy sequences that terminate:

```phel
(lazy-cat (take 3 (range 100)) (take 3 (range 10 20)))
;; => (0 1 2 10 11 12)
```

### For Recursive Sequences: Use `cons`

When building recursive infinite sequences, use `cons` instead:

```phel
;; ✅ Correct pattern for recursive sequences
(defn ints [n]
  (lazy-seq (cons n (ints (inc n)))))

(take 5 (ints 0))  ; => [0 1 2 3 4]

;; ❌ This will cause stack overflow
(defn ints [n]
  (lazy-seq (lazy-cat [n] (ints (inc n)))))  ; Don't do this!
```

**Why?** `lazy-cat` evaluates all arguments before concatenating, so the
recursive call `(ints (inc n))` runs immediately and recurses forever.
Use `cons` to defer evaluation.

## Common Patterns

### Building Infinite Sequences

```phel
;; Fibonacci sequence
(defn fib-seq
  ([] (fib-seq 0 1))
  ([a b] (lazy-seq (cons a (fib-seq b (+ a b))))))

(take 10 (fib-seq))
;; => [0 1 1 2 3 5 8 13 21 34]
```

### Filtering Infinite Sequences

```phel
(defn primes []
  (let [sieve (fn sieve [s]
                (lazy-seq
                  (cons (first s)
                        (sieve (filter (fn [x] (not= 0 (php/% x (first s))))
                                       (rest s))))))]
    (sieve (ints-from 2))))

(take 10 (primes))
;; => [2 3 5 7 11 13 17 19 23 29]
```

### Chunked Processing

```phel
;; Process a large dataset lazily
(defn process-records [records]
  (->> records
       (filter (fn [x] (not (empty? x))))
       (map (fn [x] (phel\string/trim x)))
       (map parse-record)))

;; Only processes records as needed
(take 100 (process-records lazy-data-source))
```

## Built-in Lazy Functions

Many core functions return lazy sequences:

- **`range`** - Lazy sequence of numbers
- **`iterate`** - Infinite sequence by repeatedly applying a function
- **`repeat`** - Infinite sequence of a repeated value
- **`cycle`** - Infinite sequence by cycling through a collection
- **`map`** - Lazy transformation
- **`filter`** - Lazy filtering
- **`take`** - Takes first n elements (realizes them)
- **`drop`** - Skips first n elements (lazy)

### Examples

```phel
;; Infinite sequence of powers of 2
(take 10 (iterate (fn [x] (* 2 x)) 1))
;; => [1 2 4 8 16 32 64 128 256 512]

;; Infinite cycling
(take 7 (cycle [:a :b :c]))
;; => [:a :b :c :a :b :c :a]

;; Lazy composition
(->> (range 100)
     (filter (fn [x] (= 0 (php/% x 2))))
     (map (fn [x] (* x x)))
     (take 5))
;; => [0 4 16 36 64]

;; Lazy string processing
(take 3 (map php/strtoupper "hello world"))
;; => ["H" "E" "L"]
```

## Performance Considerations

### When to Use Lazy Sequences

**Use lazy sequences when:**
- Working with large or infinite collections
- Not all elements will be consumed
- Composition of transformations is important
- Memory efficiency matters

**Avoid lazy sequences when:**
- All elements will be accessed immediately
- Multiple iterations over the same sequence
- Holding onto the head causes memory leaks

### Chunking

Phel's lazy sequences realize elements in chunks rather than one at a time. This reduces overhead but means:

```phel
;; Side effects may happen in chunks
(take 5 (map (fn [x] (do (println x) x)) (range 100)))
;; May print more than 5 numbers due to chunking
```

### Realizing Sequences

Force realization when needed:

```phel
(doall lazy-seq)  ; Realizes entire sequence, returns it
(dorun lazy-seq)  ; Realizes entire sequence, returns nil
```

## Common Gotchas

### 1. Holding the Head

```phel
;; ❌ Bad - holds reference to the head
(let [nums (range 1000000)]
  (println (first nums))
  (println (last nums)))  ; Entire sequence held in memory!

;; ✅ Good - don't hold the head
(println (first (range 1000000)))
(println (last (range 1000000)))
```

### 2. Lazy Sequences in Tests

Realize lazy sequences before asserting:

```phel
;; ❌ May not fail even if lazy-seq has issues
(is (= expected lazy-result))

;; ✅ Better - force realization
(is (= expected (doall lazy-result)))
```

### 3. Side Effects in Lazy Sequences

Side effects run on realization, not creation:

```phel
(def log-and-inc
  (map (fn [x] (do (println "Processing" x) (inc x)))
       (range 5)))

;; Nothing printed yet!
(first log-and-inc)  ; Now prints "Processing 0" and returns 1
```

## Debugging Lazy Sequences

### Check if Realized

```phel
(realized? my-lazy-seq)  ; true if already computed
```

### Inspect without Realizing

```phel
;; Take a small sample for debugging
(take 10 potentially-infinite-seq)

;; Or use take-while with a condition
(take-while (fn [x] (< x 100)) (iterate inc 0))
```

### Print Safely

```phel
;; Limit printing to avoid infinite loops
(println (take 20 my-lazy-seq))
```

## Further Reading

- Lazy sequence examples: `docs/examples/`
- Core function reference: docstrings in `src/phel/core.phel`
- Clojure lazy sequences: https://clojure.org/reference/sequences

## Summary

Lazy sequences provide:
- **Memory efficiency**: process large datasets without loading everything
- **Composability**: chain transformations
- **Infinite sequences**: work with conceptually infinite collections
- **Performance**: compute only what's needed

Key takeaways:
- Use `lazy-seq` for custom lazy sequences
- Use `cons` (not `lazy-cat`) for recursive infinite sequences
- Watch for chunking and head retention
- Force realization with `doall`/`dorun` when needed
