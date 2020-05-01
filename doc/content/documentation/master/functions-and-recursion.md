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
(fn []) # Function with no arguments that returns nil.
(fn [x] x) # The identitiy function
(fn [] 1 2 3) # A function that returns 3
(fn [a b] (+ a b)) # A function that returns the sum of a and b
```

Function can also be defined as variadic function with an infite amount of arguments using the `&` separator.

```phel
(fn [& args] (count args)) # A variadic function that counts the arguments
(fn [a b c &]) # A variadic function with extra arguments ignored
```

Phel functions are equivalent to PHP functions.


## Global functions

Global funcitons can be defined using `defn`.

```phel
(defn my-add-function [a b]
  (+ a b))
```

Each global function can take an optional doc comment.

```phel
(defn my-add-function 
  "adds value a and b"
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
Calls the function with the given arguments. The last argument must be a list of values, which are passed as seperate arguments, rather than a single list. Apply returns the result of the calling function.

```phel
(apply + [1 2 3]) # Evaluates to 6
(apply + 1 2 [3]) # Evaluates to 6
(apply + 1 2 3) # BAD! Last element must be a list
```