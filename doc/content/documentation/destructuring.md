+++
title = "Destructuring"
weight = 10
+++

Destructuring is a way to bind names to values inside a data structure. Destructuring works for function parameters, `let` and `loop` bindings.

Sequential data structures can be extract using the vector syntax.

```phel
(let [[a b] [1 2]]
  (+ a b)) # Evaluates to 3

(let [[a [b c]] [1 [2 3]]]
  (+ a b c)) # Evaluates to 6

(let [[a _ b] [1 2 3]]
  (+ a b)) # Evaluates to 4

(let [[a b & rest] [1 2 3 4]]
  (apply + a b rest)) # Evaluates to 10
```

Associative data structures can be extracted using the map syntax.

```phel
(let [{:a a :b b} {:a 1 :b 2}]
  (+ a b)) # Evaluates to 3

(let [{:a [a b] :c c} {:a [1 2] :c 3}]
  (+ a b c)) # Evaluates to 6
```

Indexed sequential can also be extracted by indices using the map syntax.

```phel
(let [{0 a 1 b} [1 2]]
  (+ a b)) # Evaluates to 3

(let [{0 [a b] 1 c} [[1 2] 3]]
  (+ a b c)) # Evaluates to 6
```
