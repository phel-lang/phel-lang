+++
title = "Special forms"
weight = 10000
+++

## Definition (def)

```phel
(def name meta+ value)
```
This special form binds a value to a global symbol. A definition can not be redefined at a later point.

```phel
(def my-name "phel")
(def sum-of-three (+ 1 2 3))
```

To each definition a list of metadata can be attached. Metadata is either a keyword or a string that is used as docstring.

```phel
(def my-private-variable :private 12)
(def my-name "Stores the name of this language" "Phel")
```

## Function (fn)

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
(fn [& args] (lenght args)) # A variadic function that counts the arguments
(fn [a b c &]) # A variadic function with extra arguments ignored
```

Phel functions are equivalent to PHP functions.

## Control flow (if)

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
(if @[] (print 1) (print 2)) # prints 2
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

## Apply

```phel
(apply f expr*)
```
Calls the function with the given arguments. The last argument must be a list of values, which are passed as seperate arguments, rather than a single list. Apply returns the result of the calling function.

```phel
(apply + [1 2 3]) # Evaluates to 6
(apply + 1 2 [3]) # Evaluates to 6
(apply + 1 2 3) # BAD! Last element must be a list
```

## Local bindings (let)

```phel
(let [bindings*] expr*)
```
Creates a new lexical context with variables defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given `nil` is returned.

```phel
(let [x 1
      y 2]
  (+ x y)) # Evaluates to 3

(let [x 1
      y (+ x 2)]) # Evaluates to nil
```
All variables defined in _bindings_ are immutable and can not be changed.

## Throw

```phel
(throw expr)
```

The _expr_ is evaluated and thrown, therefore _expr_ must return a value that implements PHP's `Throwable` interface.

## Try

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

## Loops and recursion (loop and recur)

```phel
(loop [bindings*] expr*)
```

The `loop` special form is exactly like `let` but also defines a recursion point at the top of the loop, with arity equal to the number of of bindings. This is mostly used in conjuction with `recur`.

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

(fn [sum 0 cnt 10]
  (if (= cnt 0)
    sum
    (recur (+ cnt sum) (dec cnt))))
```

## Quote

```phel
(quote form)
```
Yields the unevaluated _form_. Preceding a form with a single quote is a shorthand for `(quote form)`.

```phel
(quote 1) # Evaluates to 1
(quote hi) # Evaluates the symbol hi
(quote quote) # Evaluates to the symbol quote

'(1 2 3) # Evaluates to the tuple (1 2 3)
'(print 1 2 3) # Evaluates to the tuple (print 1 2 3). Nothing is printed.
```

## Namespace (ns)

```phel
(ns name imports*)
```

Defines the namespace for the current file add required imports to the environment. Imports can either be _uses_ or _requires_. _Uses_ are to import PHP classes and _require_ is used to import Phel modules.

```phel
(ns my\namespace\module
  (:use Some\Php\Class)
  (:require my\phel\module))
```

## Foreach

```phel
(foreach [value valueExpr] expr*)
(foreach [key value valueExpr] expr*)
```
The `foreach` special form can be used to iterate over all kind of PHP datastructures. The return value of `foreach` is always `nil`. The `loop` special form should be prefered of the `foreach` special form whenever possible.

```
(foreach [v [1 2 3]]
  (print v)) # prints 1, 2 and 3

(foreach [k v #{"a" 1 "b" 2}]
  (print k)
  (print v)) # prints "a", 1, "b" and 2
```

## PHP class instantiation

```phel
(php/new expr args*)
```

Evaluates `expr` and creates a new PHP class using the arguments. The instance of the class is returned.

```phel
(ns my\module
  (:use \DateTime))

(php/new DateTime) # Returns a new instance of the DateTime class
(php/new DateTime "now") # Returns a new instance of the DateTime class

(php/new "\\DateTimeImmutable") # instantiate a new PHP class from string
```

## PHP method and property call

```phel
(php/-> (methodname expr*))
(php/-> property)
```

Calls a method or property on a PHP object. Both _methodname_ and _property_ must symbols and can not be a evaluated value.

```phel
(ns my\module
  (:use \DateInterval))

(def di (php/new \DateInterval "PT30S"))

(php/-> di (format "%s seconds")) # Evaluates to "30 seconds"
(php/-> di s) # Evaluates to 30
```

## PHP static method and property call

```phel
(php/:: (methodname expr*))
(php/:: property)
```

Same as above, but for static calls on PHP classes.

```phel
(ns my\module
  (:use \DateTimeImmutable))

(php/:: DateTimeImmutable ATOM) # Evaluates to "Y-m-d\TH:i:sP"

# Evaluates to a new instance of DateTimeImmutable
(php/:: DateTimeImmutable (createFromFormat "Y-m-d" "2020-03-22")) 

```

## Get PHP-Array value

```phel
(php/aget arr index)
```

Equivalent to PHP's `arr[index] ?? null`.

```phel
(php/aget ["a" "b" "c"] 0) # Evaluates to "a"
(php/aget (php/array "a" "b" "c") 1) # Evaluates to "1"
(php/aget (php/array "a" "b" "c") 5) # Evaluates to nil
```

## Set PHP-Array value

```phel
(php/aset arr index value)
```

Equivalent to PHP's `arr[index] = value`.

## Append PHP-Array value

```phel
(php/apush arr value)
```

Equivalent to PHP's `arr[] = value`.

## Unset PHP-Array value

```phel
(php/aunset arr index)
```

Equivalent to PHP's `unset(arr[index])`.