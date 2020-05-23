+++
title = "Array"
weight = 9
+++

An array is a mutable indexed sequencial datastructure. In contrast to PHP arrays, Phel arrays can not be used as Map, HashTable or Dictionary. An array can be created use the `@[` reader macro or the `array` function.

```phel
@["a" "b" "c"]
(array "a" "b" "c")
```

## Getting values

Similar to tuples the functions `get`, `first`, `second`, `next` and `rest` can be used to get values of an array.

```phel
(get @[1 2 3] 2) # Evaluates to 3
(get @[] 2) # Evaluates to nil

(first @[1 2 3]) # Evaluates to 1
(first @[]) # Evaluates to nil

(second @[1 2 3]) # Evaluates to 2
(second @[]) # Evaluates to nil

(rest @[1 2 3]) # Evaluates to [2 3]
(rest @[]) # Evaluates to []

(next @[1 2 3]) # Evaluates to [2 3]
(next @[]) # Evaluates to nil
```

## Setting values

To set a value on an array, use the `put` function. If an index is given that is past the end of the array, the array is first padded with `nil`.

```phel
(let [a @[]]
  (put a 0 :hello)  # @[:hello]
  (put a 2 :world)) # @[:hello nil :world]
```

## Remove values

To remove a single value by index from an array the `unset` function can be used.

```phel
(unset @[:a :b :c :d] 1) # @[:a :c :d]
```

To remove multiple value the `remove` and `slice` function can be used. The function `remove` removes the values from the data structures and returns the removed values. The `slice` function on the otherhand, returns a copy of the array with only the `sliced` elements. The original data structure is left untouched.

```phel
(let [xs @[1 2 3 4]]
  (remove xs 2)) # Evaluates to @[3 4], xs is now @[1 2]

(let [xs @[1 2 3 4]]
  (remove xs 2 1)) # Evaluates to @[3], xs is now @[1 2 4]

(slice @[1 2 3 4] 2) # Evaluates to @[3 4]
(slice @[1 2 3 4] 2 1) # Evaluates to @[3]
```

## Array length

To count the number of element inside an array, the `count` function can be used.

```phel
(count @[1 2 3]) # Evaluates to 3
(count @[]) # Evaluates to 0
```

## Array as a Stack

An array can also be used as a stack. Therefore the `push`, `peek` and `pop` functions can be used. The `push` function add a new value to the stack. The `peek` function returns the last element on the stack but does not remove it. The `pop` function returns the last element on the stack and removes it.

```phel
(let [arr @[]]
  (push arr 1) # -> @[1]
  (peek arr) # Evaluates to 1, arr is unchanged
  (pop arr)) # Evaluates to 1, arr is not empty @[]
```