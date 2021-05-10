+++
title = "Control flow"
weight = 5
+++

## If

```phel
(if test then else?)
```

A control flow structure. First evaluates _test_. If _test_ evaluates to `true`, only the _then_ form is evaluated and the result is returned. If _test_ evaluates to `false` only the _else_ form is evaluated and the result is returned. If no _else_ form is given, `nil` will be returned.

The _test_ evaluates to `false` if its value is `false` or equal to `nil`. Every other value evaluates to `true`. In sense of PHP this means (`test != null && test !== false`).

```phel
(if true 10) # evaluates to 10
(if false 10) # evaluates to nil
(if true (print 1) (print 2)) # prints 1 but not 2
(if 0 (print 1) (print 2)) # prints 2
(if nil (print 1) (print 2)) # prints 2
(if [] (print 1) (print 2)) # prints 2
```

## Case

```phel
(case test & pairs)
```

Evaluates the _test_ expression. Then iterates over each pair. If the result of the test expression matches the first value of the pair, the second expression of the pair is evaluated and returned. If no match is found, returns nil.

```phel
(case (+ 7 5)
  3 :small
  12 :big) # Evaluates to :big

(case (+ 7 5)
  3 :small
  15 :big) # Evaluates to nil

(case (+ 7 5)) # Evalutes to nil
```

## Cond

```phel
(cond & pairs)
```

Iterates over each pair. If the first expression of the pair evaluates to logical true, the second expression of the pair is evaluated and returned. If no match is found, returns nil.

```phel
(cond
  (neg? 5) :negative
  (pos? 5) :positive)  # Evaluates to :positive

(cond
  (neg? 5) :negative
  (neg? 3) :negative) # Evaluates to nil

(cond) # Evaluates to nil
```

## Loop

```phel
(loop [bindings*] expr*)
```
Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.

```phel
(recur expr*)
```
Evaluates the expressions in order and rebinds them to the recursion point. A recursion point can be either a `fn` or a `loop`. The recur expressions must match the arity of the recursion point exactly.

Internally `recur` is implemented as a PHP while loop and therefore prevents the _Maximum function nesting level_ errors.

```phel
(loop [sum 0
       cnt 10]
  (if (= cnt 0)
    sum
    (recur (+ cnt sum) (dec cnt))))

(fn [sum cnt]
  (if (= cnt 0)
    sum
    (recur (+ cnt sum) (dec cnt))))
```

## Foreach

```phel
(foreach [value valueExpr] expr*)
(foreach [key value valueExpr] expr*)
```
The `foreach` special form can be used to iterate over all kind of PHP datastructures. The return value of `foreach` is always `nil`. The `loop` special form should be preferred of the `foreach` special form whenever possible.

```
(foreach [v [1 2 3]]
  (print v)) # prints 1, 2 and 3

(foreach [k v #{"a" 1 "b" 2}]
  (print k)
  (print v)) # prints "a", 1, "b" and 2
```

## For

A more powerful loop functionality is provided by the `for` loop. The `for` loop is a elegant way to define and create arrays based on existing collections. It combines the functionality of `foreach`, `let` and `if` in one call.

```phel
(for head body+)
```

The `head` of the loop is a vector that contains a
sequence of bindings and modifiers. A binding is a sequence of three
values `binding :verb expr`. Where `binding` is a binding as
in `let` and `:verb` is one of the following keywords:

* `:range` loop over a range, by using the range function.
* `:in` loops over all values of a collection.
* `:keys` loops over all keys/indexes of a collection.
* `:pairs` loops over all key value pairs of a collection.

After each loop binding additional modifiers can be applied. Modifiers
have the form `:modifier argument`. The following modifiers are supported:

* `:while` breaks the loop if the expression is falsy.
* `:let` defines additional bindings.
* `:when` only evaluates the loop body if the condition is true.

```phel
(for [x :range [0 3]] x) # Evaluates to [1 2 3]
(for [x :range [3 0 -1]] x) # Evaluates to [3 2 1]

(for [x :in [1 2 3]] (inc x)) # Evaluates to [2 3 4]
(for [x :in {:a 1 :b 2 :c 3}] x) # Evaluates to [1 2 3]

(for [x :keys [1 2 3]] x) # Evaluates to [0 1 2]
(for [x :keys {:a 1 :b 2 :c 3}] x) # Evaluates to [:a :b :c]

(for [[k v] :pairs {:a 1 :b 2 :c 3}] [v k]) # Evaluates to [[1 :a] [2 :b] [3 :c]]
(for [[k v] :pairs [1 2 3]] [k v]) # Evaluates to [[0 1] [1 2] [2 3]]

(for [x :in [2 2 2 3 3 4 5 6 6] :while (even? x)] x) # Evalutes to [2 2 2]
(for [x :in [2 2 2 3 3 4 5 6 6] :when (even? x)] x) # Evalutaes to [2 2 2 4 6 6]

(for [x :in [1 2 3] :let [y (inc x)]] [x y]) # Evaluates to [[1 2] [2 3] [3 4]]

(for [x :range [0 4] y :range [0 x]] [x y]) # Evaluates to [[1 0] [2 0] [2 1] [3 0] [3 1] [3 2]]
```

## Exceptions

```phel
(throw expr)
```

The _expr_ is evaluated and thrown, therefore _expr_ must return a value that implements PHP's `Throwable` interface.

## Try, Catch and Finally

```phel
(try expr* catch-clause* finally-clause?)
```

All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching _catch-clause_ is provided, its expression is evaluated and the value is returned. If no matching _catch-clause_ can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally _finally-clause_ is evaluated.

```phel
(try) # evaluates to nil

(try
  (throw (php/new Exception))
  (catch Exception e "error")) # evaluates to "error"

(try
  (+ 1 1)
  (finally (print "test"))) # Evaluates to 2 and prints "test"

(try
  (throw (php/new Exception))
  (catch Exception e "error")
  (finally (print "test"))) # evaluates to "error" and prints "test"
```

## Statements (do)

```phel
(do expr*)
```

Evaluates the expressions in order and returns the value of the last expression. If no expression is given, `nil` is returned.

```phel
(do 1 2 3 4) # Evaluates to 4
(do (print 1) (print 2) (print 3)) # Print 1, 2, and 3
```
