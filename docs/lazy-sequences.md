# Lazy Sequences in Phel

Defer computation until values are needed. Two constructs: `lazy-seq` wraps an expression; `lazy-cat` concatenates collections.

## `lazy-seq`

Takes a body returning a sequence or nil. Wraps it in a zero-arg thunk and returns a LazySeq that, on first access (`first`, `rest`, `take`, ...), evaluates the body and caches the result.

```phel
(def my-lazy-seq
  (lazy-seq
    (println "Computing...")
    [1 2 3 4 5]))

(first my-lazy-seq)  ; prints "Computing..." then returns 1
(first my-lazy-seq)  ; returns 1 (cached, no printing)
```

### Infinite sequences

Combine `lazy-seq` with recursion. Use `cons` to defer the recursive call:

```phel
(defn ints-from [^int n]
  (lazy-seq
    (cons n (ints-from (inc n)))))

(take 5 (ints-from 0))  ; => [0 1 2 3 4]
(take 3 (ints-from 10)) ; => [10 11 12]
```

## `lazy-cat`

Concatenates collections; expands to `concat` and evaluates its arguments eagerly. Fine for finite or already-realized sequences, **not** for recursive infinite ones.

```phel
(lazy-cat [1 2] [3 4] [5 6])               ; => (1 2 3 4 5 6)
(lazy-cat (range 3) (range 3 6))           ; => (0 1 2 3 4 5)
(lazy-cat (take 3 (range 100)) (take 3 (range 10 20))) ; => (0 1 2 10 11 12)
```

For recursive infinite sequences use `cons`, never `lazy-cat`:

```phel
;; ✅ cons defers the recursive call
(defn ints [n]
  (lazy-seq (cons n (ints (inc n)))))

(take 5 (ints 0))  ; => [0 1 2 3 4]

;; ❌ lazy-cat evaluates all args first -> the recursive call never returns
(defn ints [n]
  (lazy-seq (lazy-cat [n] (ints (inc n)))))  ; stack overflow
```

## Common patterns

```phel
;; Fibonacci
(defn fib-seq
  ([] (fib-seq 0 1))
  ([a b] (lazy-seq (cons a (fib-seq b (+ a b))))))

(take 10 (fib-seq))
;; => [0 1 1 2 3 5 8 13 21 34]

;; Sieve of primes (filtering an infinite sequence)
(defn primes []
  (let [sieve (fn sieve [s]
                (lazy-seq
                  (cons (first s)
                        (sieve (filter (fn [x] (not= 0 (mod x (first s))))
                                       (rest s))))))]
    (sieve (ints-from 2))))

(take 10 (primes))
;; => [2 3 5 7 11 13 17 19 23 29]

;; Chunked processing - only touches records as consumed
;; assumes (:require phel.string :as str)
(defn process-records [records]
  (->> records
       (filter (fn [x] (not (empty? x))))
       (map (fn [x] (str/trim x)))
       (map parse-record)))

(take 100 (process-records lazy-data-source))
```

## Built-in lazy functions

These return lazy sequences:

- `range` - lazy sequence of numbers
- `iterate` - infinite sequence by repeatedly applying a function
- `repeat` - infinite sequence of a repeated value
- `cycle` - infinite sequence by cycling through a collection
- `map` - lazy transformation
- `filter` - lazy filtering
- `take` - first n elements (realizes them)
- `drop` - skips first n elements (lazy)

```phel
(take 10 (iterate (fn [x] (* 2 x)) 1))
;; => [1 2 4 8 16 32 64 128 256 512]

(take 7 (cycle [:a :b :c]))
;; => [:a :b :c :a :b :c :a]

(->> (range 100)
     (filter even?)
     (map (fn [x] (* x x)))
     (take 5))
;; => [0 4 16 36 64]

(take 3 (map php/strtoupper "hello world"))
;; => ["H" "E" "L"]
```

## Performance

**Use lazy sequences for:** large or infinite collections, partial consumption, composing transformations, memory efficiency.

**Avoid when:** all elements are accessed immediately, you iterate the same sequence multiple times, or holding the head leaks memory.

### Chunking

Lazy sequences may realize more elements than you consume (e.g. `take` forces one extra), so side effects can run for elements you never read:

```phel
(take 5 (map (fn [x] (do (println x) x)) (range 100)))
;; may print more than 5 numbers due to chunking
```

### Realizing

```phel
(doall lazy-seq)  ; realizes entire sequence, returns it as a vector
(dorun lazy-seq)  ; realizes entire sequence for side effects, returns nil
```

## Gotchas

**1. Holding the head** keeps the whole sequence in memory:

```phel
;; ❌ binds `nums`, so first + last hold the entire sequence
(let [nums (range 1000000)]
  (println (first nums))
  (println (last nums)))

;; ✅ don't bind the head
(println (first (range 1000000)))
(println (last (range 1000000)))
```

**2. Lazy sequences in tests** - realize before asserting:

```phel
(is (= expected (doall lazy-result)))  ; ✅ force realization
```

**3. Side effects run on realization, not creation:**

```phel
(def log-and-inc
  (map (fn [x] (do (println "Processing" x) (inc x)))
       (range 5)))
;; nothing printed yet
(first log-and-inc)  ; now prints "Processing 0" and returns 1
```

## Debugging

```phel
(realized? my-lazy-seq)  ; true if already computed

;; Inspect without fully realizing
(take 10 potentially-infinite-seq)
(take-while (fn [x] (< x 100)) (iterate inc 0))

;; Limit printing to avoid infinite loops
(println (take 20 my-lazy-seq))
```

## Further reading

- [Lazy sequences examples](examples/README.md)
- [Transducers](transducers.md)
- [Async Guide](async-guide.md)
- Core function reference: docstrings in `src/phel/core/`
- Clojure lazy sequences: https://clojure.org/reference/sequences
