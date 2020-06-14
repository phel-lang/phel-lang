+++
title = "Tuple"
weight = 8
+++

A Tuple is an immutable sequential data structure. The two most common ways to create a tuple is using the literal form with bracket characters or calling the tuple function directly.

```phel
[1 2 3]
(tuple 1 2 3)
```

## Getting values

There are multiple ways to get an element from a Tuple. The most common one is the `get` function that takes as first element the Tuple and as second element the index of the value that should be returned. Based on `get` there are also `first` and `second`. The `first` function returns the first element of the Tuple and `second` returns the second element of the Tuple.

```phel
(get [1 2 3] 2) # Evaluates to 3
(get [] 2) # Evaluates to nil

(first [1 2 3]) # Evaluates to 1
(first []) # Evaluates to nil

(second [1 2 3]) # Evaluates to 2
(second []) # Evaluates to nil
```

The function `rest` and `next` can be used to return a new Tuple without the first element. The difference between `rest` and `next` is the behaviour on an empty Tuple. The `rest` function returns a new empty Tuple. The `next` function instead, returns a nil.

```phel
(rest [1 2 3]) # Evaluates to [2 3]
(rest []) # Evaluates to []
(next [1 2 3]) # Evaluates to [2 3]
(next []) # Evaluates to nil
```

## Appending values

To append a value at the beginning or at the end of a Tuple `cons` and `push` can be used.

```phel
(cons 1 [2]) # Evaluates to [1 2]
(push [1] 2) # Evaluates to [1 2]
```

Similar the `concat` function can be used to concatenate two tuples.

```phel
(concat [1 2] [3 4]) # Evaluates to [1 2 3 4]
```

## Length of a Tuple

To count the number of element inside a Tuple the `count` function can be used.

```phel
(count [1 2 3]) # Evaluates to 3
(count []) # Evaluates to 0
```