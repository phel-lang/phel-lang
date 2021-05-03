+++
title = "Functions and Recursion"
weight = 7
+++

## Anonymous Function (fn)

```phel
(fn [params*] expr*)
```

Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns `nil`.

Function also introduce a new lexical scope that is not accessible outside of the function.

```phel
(fn []) # Function with no arguments that returns nil
(fn [x] x) # The identity function
(fn [] 1 2 3) # A function that returns 3
(fn [a b] (+ a b)) # A function that returns the sum of a and b
```

Function can also be defined as variadic function with an infite amount of arguments using the `&` separator.

```phel
(fn [& args] (count args)) # A variadic function that counts the arguments
(fn [a b c &]) # A variadic function with extra arguments ignored
```

There is a shorter form to define an anonymous function. This omits the parameter list and names parameters based on their position.

* `$` is used for a single parameter
* `$1`, `$2`, `$3`, etc are used for multiple parameters
* `$&` is used for the remaining variadic parameters

```phel
|(+ 6 $) # Same as (fn [x] (+ 6 x))
|(+ $1 $2) # Same as (fn [a b] (+ a b))
|(sum $&) # Same as (fn [& xs] (sum xs))
```


## Global functions

```phel
(defn docstring? attributes? [params*] expr*)
```

Global functions can be defined using `defn`.

```phel
(defn my-add-function [a b]
  (+ a b))
```

Each global function can take an optional doc comment and attribute map.

```phel
(defn my-add-function
  "adds value a and b"
  [a b]
  (+ a b))

(defn my-private-add-function
  "adds value a and b"
  {:private true}
  [a b]
  (+ a b))
```

## Recursion

Similar to `loop`, functions can be made recursive using `recur`.

```phel
(fn [x]
  (if (php/== x 0)
    x
    (recur (php/- x 1))))

(defn my-recur-fn [x]
  (if (php/== x 0)
    x
    (recur (php/- x 1))))
```

## Apply functions

```phel
(apply f expr*)
```
Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.

```phel
(apply + [1 2 3]) # Evaluates to 6
(apply + 1 2 [3]) # Evaluates to 6
(apply + 1 2 3) # BAD! Last element must be a list
```

## Passing by reference

Sometimes it is required that a variable should pass to a function by reference. This can be done by applying the `:reference` metadata to the symbol.

```phel
(fn [^:reference my-arr]
  (php/apush my-arr 10))
```

Support for references is very limited in Phel. Currently, it only works for function arguments (except destructuring).
