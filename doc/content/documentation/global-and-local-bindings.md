+++
title = "Global and local bindings"
weight = 6
+++

## Definition (def)

```phel
(def name meta? value)
```
This special form binds a value to a global symbol. A definition cannot be redefined at a later point.

```phel
(def my-name "phel")
(def sum-of-three (+ 1 2 3))
```

To each definition metadata can be attached. Metadata is either a Keyword, a String or a Map.

```phel
(def my-private-variable :private 12)
(def my-name "Stores the name of this language" "Phel")
(def my-other-name {:private true :doc "This is my doc"} "My value")
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
All variables defined in _bindings_ are immutable and cannot be changed.
