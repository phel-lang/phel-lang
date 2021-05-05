+++
title = "Data structures"
weight = 9
+++

Phel has four main data structures. All data structures are persistent data structures. A persistent data structure preserves the previous version of itself when it is modified. Such data structures are also called immutable data structures. The difference of persistent data structures and immutable data structures is that immutable data structures copy the whole data structure, while persistent data structures share unmodified values with their previous versions.

## Lists

A persistent list is simple a linked list. Access or modifications on the first element is efficient, random access is not. In Phel, a list has a special meaning. They are interpreted as function calls, macro calls or special forms by the compiler.

To create a list surrond the white space separeted values with parentheses or use the `list` function.

```phel
(do 1 2 3) # list with 4 entries
(list 1 2 3) # use the list function to create a new list
'(1 2 3) # use a quote to create a list
```

To access values in a list the functions `get`, `first`, `second`, `next` and `rest` can be used.

```phel
(get (list 1 2 3) 0) # Evaluates to 1
(first (list 1 2 3)) # Evaluates to 1
(second (list 1 2 3)) # Evaluates to 2
(next (list 1 2 3)) # Evaluates to (2 3)
(next (list)) # Evaluates to nil
(rest (list 1 2 3)) # Evaluates to (2 3)
(rest (list)) # Evaluates to ()
```

New values can only be added to the front of the list with the `cons` function.

```phel
(cons 1 (list)) # Evaluates to (1)
(cons 3 (list 1 2)) # Evaluates to (3 1 2)
```

To get the length of the list the `count` function can be used

```phel
(count (list)) # Evaluates to 0
(count (list 1 2 3)) # Evaluates to 3
```

## Vectors

Vectors are an indexed, sequential data structure. They offer efficient random access (by index) and are very efficient in appending values at the end.

To create a vector wrap the white space seperated values with brackets or use the `vector` function.

```phel
[1 2 3] # Creates a new vector with three values
(vector 1 2 3) # Creates a new vector with three values
```

To get a value by it's index use the `get` function. Similar to list you can use the `first` and `second` function to access the first or second values of the vector.

```phel
(get [1 2 3] 0) # Evaluates to 1
(first [1 2 3]) # Evaluates to 1
(second [1 2 3]) # Evaluates to 2
```

New values can be appended by using the `push` function.

```phel
(push [1 2 3] 4) # Evaluates to [1 2 3 4]
```

To change an exsting value use the `put` function

```phel
(put [1 2 3] 0 4) # Evaluates to [4 2 3]
(put [1 2 3] 3 4) # Evaluates to [1 2 3 4]
```

A vector can be counted using the `count` function.

```phel
(count []) # Evaluates to 0
(count [1 2 3]) # Evaluates to 3
```

## Maps

A Map contains key-value-pairs in random order. Each possible key appears at most once in the collection. In contrast to PHP's associative arrays, Phel Maps can have any type of keys that implement the `HashableInterface` and the `EqualsInterface`.

To create a map wrap the key and values in curly brackets or use the `hash-map` function.

```phel
{:key1 value1 :key2 value2} # Create a new map with two key-value-pairs
(hash-map :key1 value1 :key2 value2) # Create a new map using the hash-map function
```

Use the `get` function to access a value by it's key

```phel
(get {:a 1 :b 2} :a) # Evaluates to 1
(get {:a 1 :b 2} :b) # Evaluates to 2
(get {:a 1 :b 2} :c) # Evaluates to nil
```

To add or update a key-value pair in the map use the `put` function

```phel
(put {} :a "hello") # Evaluates to {:a "hello"}
(put {:a "foo"} :a "bar") # Evaluates to {:a "bar"}
```

A value in a map can be removed with the `unset` function

```phel
(unset {:a "foo"} :a) # Evaluates to {}
```

As in the other data structures, the `count` function can be used to count the key-value-pairs.

```phel
(count {}) # Evaluates to 0
(count {:a "foo"}) # Evaluates to 1
```

## Structs

A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.

```phel
(defstruct my-struct [a b c]) # Defines the struct
(let [x (my-struct 1 2 3)] # Create a new struct
  (my-struct? x) # Evaluates to true
  (get x :a) # Evaluates to 1
  (put x :a 12) # Evaluates to (my-struct 12 2 3)
```

Internally, Phel Structs are PHP classes where each key correspondence to a object property. Therefore, Structs can be faster than Maps.

## Sets

A Set contains unique values in random order. All types of values are allowed that implement the `HashableInterface` and the `EqualsInterface`.

A new set can be created by using the `set` function

```phel
(set 1 2 3) # Creates a new set with three values
```

The `push` function can be used to add a new value to the Set.

```phel
(push (set 1 2 3) 4) # Evaluates to (set 1 2 3 4)
(push (set 1 2 3) 2) # Evaluates to (set 1 2 3)
```

Similar to the Map the `unset` function can be used to remove a value from the list

```phel
(unset (set 1 2 3) 2) # Evaluates to (set 1 3)
```

Again the `count` function can be used to count the elements in the set

```phel
(count (set)) # Evaluates to 0
(count (set 2)) # Evaluates to 1
```

Additionally, the union of a collection of sets is the set of all elements in the collection.

```phel
(union) # Evaluates to (set)
(union (set 1 2)) # Evaluates to (set 1 2)
(union (set 1 2) (set 0 3)) # Evaluates to (set 0 1 2 3)
```

The intersection of two sets or more is the set containing all elements shared between those sets.

```phel
(intersection (set 1 2) (set 0 3)) # Evaluates to (set)
(intersection (set 1 2) (set 0 1 2 3)) # Evaluates to (set 1 2)
```

The difference of two sets or more is the set containing all elements in the first set that aren't in the other sets.

```phel
(difference (set 1 2) (set 0 3)) # Evaluates to (set 1 2)
(difference (set 1 2) (set 0 1 2 3)) # Evaluates to (set)
(difference (set 0 1 2 3) (set 1 2)) # Evaluates to (set 0 3)
```

The symmetric difference of two sets or more is the set of elements which are in either of the sets and not in their intersection.

```phel
(symmetric-difference (set 1 2) (set 0 3)) # Evaluates to (set 0 1 2 3)
(symmetric-difference (set 1 2) (set 0 1 2 3)) # Evaluates to (set 0 3)
```

## Data structures are functions

In Phel all data structures can also be used as functions.

```phel
((list 1 2 3) 0) # Same as (get (list 1 2 3) 0)
([1 2 3] 0) # Same as (get [1 2 3] 0)
({:a 1 :b 2} :a) # Same as (get {:a 1 :b 2} :a)
((set 1 2 3) 1)
```

## Transients

Nearly all persistent data structures have a transient version (except for Persistent List). The transient version of each persistent data structure is a mutable version of them. It store the value in the same way as the persistent version but instead of returning a new persistent version with every modification it modifies the current version. Transient versions are a little bit faster and can be used as builders for new persistent collections. Since transients use the same underlying storage it is very fast to convert a persistent data structure to a transient and back.

For example, if we want to convert a PHP Array to a persistent map. This function can be used:

```phel
(defn php-array-to-map
  "Converts a PHP Array to a map."
  [arr]
  (let [res (transient {})] # Convert a persistent data to a transient
    (foreach [k v arr]
      (put res k v))  # Fill the transient map (mutable)
    (persistent res))) # Convert the transient map to a persistent map.
```
