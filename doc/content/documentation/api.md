+++
title = "API"
weight = 110
template = "page-api.html"
+++

## `%`

```phel
(% dividend divisor)
```
Return the remainder of `dividend` / `divisor`.

## `*`

```phel
(* & xs)
```
Returns the product of all elements in `xs`. All elements in `xs` must be
numbers. If `xs` is empty, return 1.

## `**`

```phel
(** a x)
```
Return `a` to the power of `x`.

## `*ns*`

Returns the namespace in the current scope.

## `+`

```phel
(+ & xs)
```
Returns the sum of all elements in `xs`. All elements `xs` must be numbers.
  If `xs` is empty, return 0.

## `-`

```phel
(- & xs)
```
Returns the difference of all elements in `xs`. If `xs` is empty, return 0. If `xs`
  has one element, return the negative value of that element.

## `->`

```phel
(-> x & forms)
```
Threads the expr through the forms. Inserts `x` as the second item
  in the first from, making a list of it if it is not a list already.
  If there are more froms, inserts the first form as the second item in
  the second form, etc.

## `->>`

```phel
(->> x & forms)
```
Threads the expr through the forms. Inserts `x` as the
  last item in the first form, making a list of it if it is not a
  list already. If there are more forms, inserts the first form as the
  last item in second form, etc.

## `/`

```phel
(/ & xs)
```
Returns the nominator divided by all the denominators. If `xs` is empty,
returns 1. If `xs` has one value, returns the reciprocal of x.

## `<`

```phel
(< a & more)
```
Checks if each argument is strictly less than the following argument. Returns a boolean.

## `<=`

```phel
(<= a & more)
```
Checks if each argument is less than or equal to the following argument. Returns a boolean.

## `=`

```phel
(= a & more)
```
Checks if all values are equal. Same as `a == b` in PHP.

## `>`

```phel
(> a & more)
```
Checks if each argument is strictly greater than the following argument. Returns a boolean.

## `>=`

```phel
(>= a & more)
```
Checks if each argument is greater than or equal to the following argument. Returns a boolean.

## `NAN`

Constant for Not a Number (NAN) values.

## `all?`

```phel
(all? pred xs)
```
Returns true if `(pred x)` is logical true for every `x` in `xs`, else false.

## `and`

```phel
(and & args)
```
Evaluates each expression one at a time, from left to right. If a form
returns logical false, and returns that value and doesn't evaluate any of the
other expressions, otherwise it returns the value of the last expression.
Calling the and function without arguments returns true.

## `argv`

Array of arguments passed to script.

## `array`

```phel
(array & xs)
```
Creates a new Array. If no argument is provided, an empty Array is created.

## `array?`

```phel
(array? x)
```
Returns true if `x` is a array, false otherwise.

## `as->`

```phel
(as-> expr name & forms)
```
Binds `name` to `expr`, evaluates the first form in the lexical context
  of that binding, then binds name to that result, repeating for each
  successive form, returning the result of the last form.

## `associative?`

```phel
(associative? x)
```
Returns true if `x` is associative data structure, false otherwise.

## `bit-and`

```phel
(bit-and x y & args)
```
Bitwise and

## `bit-clear`

```phel
(bit-clear x n)
```
Clear bit an index `n`

## `bit-flip`

```phel
(bit-flip x n)
```
Flip bit at index `n`

## `bit-not`

```phel
(bit-not x)
```
Bitwise complement

## `bit-or`

```phel
(bit-or x y & args)
```
Bitwise or

## `bit-set`

```phel
(bit-set x n)
```
Set bit an index `n`

## `bit-shift-left`

```phel
(bit-shift-left x n)
```
Bitwise shift left

## `bit-shift-right`

```phel
(bit-shift-right x n)
```
Bitwise shift right

## `bit-test`

```phel
(bit-test x n)
```
Test bit at index `n`

## `bit-xor`

```phel
(bit-xor x y & args)
```
Bitwise xor

## `boolean?`

```phel
(boolean? x)
```
Returns true if `x` is a boolean, false otherwise.

## `case`

```phel
(case e & pairs)
```
Takes an expression `e` and a set of test-content/expression pairs. First
  evaluates `e` and the then finds the first pair where the test-constant matches
  the result of `e`. The associated expression is then evaluated and returned.
  If no matches can be found a final last expression can be provided that is
  than evaluated and return. Otherwise, nil is returned.

## `comment`

```phel
(comment &)
```
Ignores the body of the comment.

## `comp`

```phel
(comp & fs)
```
Takes a list of functions and returns a function that is the composition
  of those functions.

## `compare`

```phel
(compare x y)
```
An integer less than, equal to, or greater than zero when `x` is less than,
  equal to, or greater than `y`, respectively.

## `complement`

```phel
(complement f)
```
Returns a function that takes the same arguments as `f` and returns the opposite truth value.

## `concat`

```phel
(concat arr & xs)
```
Concatenates multiple sequential data structures.

## `cond`

```phel
(cond & pairs)
```
Takes a set of test/expression pairs. Evaluates each test one at a time.
  If a test returns logically true, the expression is evaluated and return.
  If no test matches a final last expression can be provided that is than
  evaluated and return. Otherwise, nil is returned.

## `cons`

```phel
(cons x xs)
```
Prepends `x` to the beginning of `xs`.

## `contains?`

```phel
(contains? coll key)
```
Returns true if key is present in the given collection, otherwise returns false.

## `count`

```phel
(count xs)
```
Counts the number of elements in a sequence. Can be used on everything that
implement the PHP Countable interface.

## `dec`

```phel
(dec x)
```
Decrements `x` by one.

## `declare`

```phel
(declare name)
```
Declare a global symbol before it is defined.

## `def-`

```phel
(def- name value)
```
Define a private value that will not be exported.

## `defmacro`

```phel
(defmacro name & fdecl)
```
Define a macro.

## `defmacro-`

```phel
(defmacro- name & fdecl)
```
Define a private macro that will not be exported.

## `defn`

```phel
(defn name & fdecl)
```
Define a new global function.

## `defn-`

```phel
(defn- name & fdecl)
```
Define a private function that will not be exported.

## `defstruct`

```phel
(defstruct name keys)
```
Define a new struct.

## `difference`

```phel
(difference set & sets)
```
Difference between multiple sets into a new one.

## `difference-pair`

```phel
(difference-pair s1 s2)
```


## `distinct`

```phel
(distinct xs)
```
Returns an array with duplicated values removed in `xs`.

## `dofor`

```phel
(dofor head & body)
```
Repeatedly executes body for side effects with bindings and modifiers as
  provided by for. Returns nil.

## `drop`

```phel
(drop n xs)
```
Drops the first `n` elements of `xs`.

## `drop-while`

```phel
(drop-while pred xs)
```
Drops all elements at the front `xs` where `(pred x)` is true.

## `empty?`

```phel
(empty? x)
```
Returns true if `(count x)` is zero, false otherwise.

## `even?`

```phel
(even? x)
```
Checks if `x` is even.

## `extreme`

```phel
(extreme order args)
```
Returns the most extreme value in `args` based on the binary `order` function.

## `false?`

```phel
(false? x)
```
Checks if `x` is false. Same as `x === false` in PHP.

## `ffirst`

```phel
(ffirst xs)
```
Same as `(first (first xs))`

## `filter`

```phel
(filter pred xs)
```
Returns all elements of `xs` wher `(pred x)` is true.

## `find`

```phel
(find pred xs)
```
Returns the first item in `xs` where `(pred item)` evaluates to true.

## `find-index`

```phel
(find-index pred xs)
```
Returns the first item in `xs` where `(pred index item)` evaluates to true.

## `first`

```phel
(first xs)
```
Returns the first element of an indexed sequence or nil.

## `flatten`

```phel
(flatten xs)
```
Takes a nested sequential data structure (tree), and returns their contents
  as a single, flat array.

## `float?`

```phel
(float? x)
```
Returns true if `x` is float point number, false otherwise.

## `for`

```phel
(for head & body)
```
List comprehension. The head of the loop is a vector that contains a
  sequence of bindings and modifiers. A binding is a sequence of three
  values `binding :verb expr`. Where `binding` is a binding as
  in let and `:verb` is one of the following keywords:

  * :range loop over a range by using the range function.
  * :in loops over all values of a collection.
  * :keys loops over all keys/indexes of a collection.
  * :pairs loops over all key value pairs of a collection.

  After each loop binding additional modifiers can be applied. Modifiers
  have the form `:modifier argument`. The following modifiers are supported:

  * :while breaks the loop if the expression is falsy.
  * :let defines additional bindings.
  * :when only evaluates the loop body if the condition is true.

  The for loops returns a array with all evaluated elements of the body.

## `format`

```phel
(format fmt & xs)
```
Returns a formatted string. See PHP's [sprintf](https://www.php.net/manual/en/function.sprintf.php) for more information.

## `frequencies`

```phel
(frequencies xs)
```
Returns a table from distinct items in `xs` to the number of times they appear.

## `function?`

```phel
(function? x)
```
Returns true if `x` is a function, false otherwise.

## `gensym`

```phel
(gensym )
```
Generates a new unique symbol.

## `get`

```phel
(get ds k & [opt])
```
Get the value mapped to `key` from the datastructure `ds`.
  Returns `opt` or nil if the value cannot be found.

## `get-in`

```phel
(get-in ds ks & [opt])
```
Access a value in a nested data structure. Looks into the data structure via a sequence of keys.

## `group-by`

```phel
(group-by f xs)
```
Returns a table of the elements of xs keyed by the result of
  f on each element.

## `hash-map`

```phel
(hash-map & xs)
```
Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.

## `hash-map?`

```phel
(hash-map? x)
```
Returns true if `x` is a hash map, false otherwise.

## `http/create-response-from-map`

```phel
(create-response-from-map {:status status :headers headers :body body :version version :reason reason})
```
Creates a response struct from a map. The map can have the following keys:
  * `:status` The HTTP Status (default 200)
  * `:headers` A map of HTTP Headers (default: empty map)
  * `:body` The body of the response (default: empty string)
  * `:version` The HTTP Version (default: 1.1)
  * `:reason` The HTTP status reason. If not provided a common status reason is taken

## `http/create-response-from-string`

```phel
(create-response-from-string s)
```
Create a response from a string.

## `http/emit-response`

```phel
(emit-response response)
```
Emits the response.

## `http/files-from-globals`

```phel
(files-from-globals & [files])
```
Extracts the files from `$_FILES` and normalizes them to a map of "uploaded-file".

## `http/headers-from-server`

```phel
(headers-from-server & [server])
```
Extracts all headers from the `$_SERVER` variable.

## `http/request`

```phel
(request method uri headers parsed-body query-params cookie-params server-params uploaded-files version)
```
Creates a new request struct

## `http/request-from-globals`

```phel
(request-from-globals )
```
Extracts a request from `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` and `$_FILES`.

## `http/request-from-globals-args`

```phel
(request-from-globals-args server get-parameter post-parameter cookies files)
```
Extracts a request from args.

## `http/request?`

```phel
(request? x)
```
Checks if `x` is an instance of the request struct

## `http/response`

```phel
(response status headers body version reason)
```
Creates a new response struct

## `http/response?`

```phel
(response? x)
```
Checks if `x` is an instance of the response struct

## `http/uploaded-file`

```phel
(uploaded-file tmp-file size error-status client-filename client-media-type)
```
Creates a new uploaded-file struct

## `http/uploaded-file?`

```phel
(uploaded-file? x)
```
Checks if `x` is an instance of the uploaded-file struct

## `http/uri`

```phel
(uri scheme userinfo host port path query fragment)
```
Creates a new uri struct

## `http/uri-from-globals`

```phel
(uri-from-globals & [server])
```
Extracts the URI from the `$_SERVER` variable.

## `http/uri?`

```phel
(uri? x)
```
Checks if `x` is an instance of the uri struct

## `id`

```phel
(id a & more)
```
Checks if all values are identically. Same as `a === b` in PHP.

## `identity`

```phel
(identity x)
```
Returns its argument

## `if-not`

```phel
(if-not test then & [else])
```
Shorthand for `(if (not condition) else then)`.

## `inc`

```phel
(inc x)
```
Increments `x` by one.

## `indexed?`

```phel
(indexed? x)
```
Returns true if `x` is indexed sequence, false otherwise.

## `int?`

```phel
(int? x)
```
Returns true if `x` is an integer number, false otherwise.

## `interleave`

```phel
(interleave & xs)
```
Returns a array with the first items of each col, than the second items etc.

## `interpose`

```phel
(interpose sep xs)
```
Returns an array of elements separated by `sep`

## `intersection`

```phel
(intersection set & sets)
```
Intersect multiple sets into a new one.

## `invert`

```phel
(invert table)
```
Returns a new table where the keys and values are swapped. If table has
  duplicated values, some keys will be ignored.

## `juxt`

```phel
(juxt & fs)
```
Takes a list of functions and returns a new function that is the juxtaposition of those functions.
  `((juxt a b c) x) => [(a x) (b x) (c x)]`

## `keep`

```phel
(keep pred xs)
```
Returns a list of non-nil results of `(pred x)`.

## `keep-indexed`

```phel
(keep-indexed pred xs)
```
Returns a list of non-nil results of `(pred i x)`.

## `keys`

```phel
(keys xs)
```
Gets the keys of an associative data structure.

## `keyword`

```phel
(keyword x)
```
Creates a new Keyword from a given string.

## `keyword?`

```phel
(keyword? x)
```
Returns true if `x` is a keyword, false otherwise.

## `kvs`

```phel
(kvs xs)
```
Returns an array of key value pairs like @[k1 v1 k2 v2 k3 v3 ...].

## `list`

```phel
(list & xs)
```
Creates a new list. If no argument is provided, an empty list is created.

## `list?`

```phel
(list? x)
```
Returns true if `x` is a list, false otherwise.

## `load`

```phel
(load path)
```
Loads a file into the current namespace. It can be used to split large namespaces into multiple files.

## `map`

```phel
(map f & xs)
```
Returns an array consisting of the result of applying `f` to all of the first items in each `xs`,
   followed by applying `f` to all the second items in each `xs` until anyone of the `xs` is exhausted.

## `map-indexed`

```phel
(map-indexed f xs)
```
Applies `f` to each element in `xs`. `f` is a two argument function. The first
  argument is index of the element in the sequence and the second element is the
  element itself.

## `mapcat`

```phel
(mapcat f & xs)
```
Applies `f` on all `xs` and concatenate the result.

## `max`

```phel
(max & numbers)
```
Returns the numeric maximum of all numbers.

## `mean`

```phel
(mean xs)
```
Returns the mean of `xs`.

## `merge`

```phel
(merge & tables)
```
Merges multiple tables into one new table. If a key appears in more than one
  collection, then later values replace any previous ones.

## `merge-into`

```phel
(merge-into tab & tables)
```
Merges multiple tables into first table. If a key appears in more than one
  collection, then later values replace any previous ones.

## `meta`

```phel
(meta obj)
```
Gets the meta of the give object

## `min`

```phel
(min & numbers)
```
Returns the numeric minimum of all numbers.

## `nan?`

```phel
(nan? x)
```
Checks if `x` is not a number.

## `neg?`

```phel
(neg? x)
```
Checks if `x` is smaller than zero.

## `next`

```phel
(next xs)
```
Returns the sequence of elements after the first element. If there are no
elements, returns nil.

## `nfirst`

```phel
(nfirst xs)
```
Same as `(next (first xs))`.

## `nil?`

```phel
(nil? x)
```
Returns true if `x` is nil, false otherwise.

## `nnext`

```phel
(nnext xs)
```
Same as `(next (next xs))`

## `not`

```phel
(not x)
```
The `not` function returns `true` if the given value is logical false and
`false` otherwise.

## `not=`

```phel
(not= a & more)
```
Checks if all values are unequal. Same as `a != b` in PHP.

## `number?`

```phel
(number? x)
```
Returns true if `x` is a number, false otherwise.

## `odd?`

```phel
(odd? x)
```
Checks if `x` is odd.

## `one?`

```phel
(one? x)
```
Checks if `x` is one.

## `or`

```phel
(or & args)
```
Evaluates each expression one at a time, from left to right. If a form
returns a logical true value, or returns that value and doesn't evaluate any of
the other expressions, otherwise it returns the value of the last expression.
Calling or without arguments, returns nil.

## `pairs`

```phel
(pairs xs)
```
Gets the pairs of an associative data structure.

## `partial`

```phel
(partial f & args)
```
Takes a function `f` and fewer than normal arguments of `f` and returns a function
  that a variable number of additional arguments. When call `f` will be called
  with `args` and the additional arguments

## `partition`

```phel
(partition n xs)
```
Partition an indexed data structure into vectors of maximum size n. Returns a new vector.

## `peek`

```phel
(peek xs)
```
Returns the last element of a sequence.

## `persistent`

```phel
(persistent coll)
```
Converts a transient collection to a persistent collection

## `php-array-to-map`

```phel
(php-array-to-map arr)
```
Converts a PHP Array to a tables.

## `php-array-to-table`

```phel
(php-array-to-table arr)
```
Converts a PHP Array to a tables.

## `php-array?`

```phel
(php-array? x)
```
Returns true if `x` is a PHP Array, false otherwise.

## `php-associative-array`

```phel
(php-associative-array & xs)
```
Creates a PHP associative array. An even number of parameters must be provided.

## `php-indexed-array`

```phel
(php-indexed-array & xs)
```
Creates an PHP indexed array from the given values.

## `php-object?`

```phel
(php-object? x)
```
Returns true if `x` is a PHP object, false otherwise.

## `php-resource?`

```phel
(php-resource? x)
```
Returns true if `x` is a PHP resource, false otherwise.

## `pop`

```phel
(pop xs)
```
Removes the last element of the array `xs`. If the array is empty
  returns nil.

## `pos?`

```phel
(pos? x)
```
Checks if `x` is greater than zero.

## `print`

```phel
(print & xs)
```
Prints the given values to the default output stream. Returns nil.

## `print-str`

```phel
(print-str & xs)
```
Same as print. But instead of writing it to a output stream,
  The resulting string is returned.

## `printf`

```phel
(printf fmt & xs)
```
Output a formatted string. See PHP's [printf](https://www.php.net/manual/en/function.printf.php) for more information.

## `println`

```phel
(println & xs)
```
Same as print followed by a newline.

## `push`

```phel
(push xs x)
```
Inserts `x` at the end of the sequence `xs`.

## `put`

```phel
(put ds key value)
```
Puts `value` mapped to `key` on the datastructure `ds`. Returns `ds`.

## `put-in`

```phel
(put-in ds [k & ks] v)
```
Puts a value into a nested data structure.

## `rand`

```phel
(rand )
```
Returns a random number between 0 and 1.

## `rand-int`

```phel
(rand-int n)
```
Returns a random number between 0 and `n`.

## `rand-nth`

```phel
(rand-nth xs)
```
Returns a random item from xs.

## `range`

```phel
(range a & rest)
```
Create an array of values [start, end). If the function has one argument the
  the range [0, end) is returned. With two arguments, returns [start, end).
  The third argument is an optional step width (default 1).

## `re-seq`

```phel
(re-seq re s)
```
Returns a sequence of successive matches of pattern in string.

## `reduce`

```phel
(reduce f init xs)
```
Transforms an collection `xs` with a function `f` to produce a value by applying `f` to each element in order.
  `f` is a function with two arguments. The first argument is the initial value and the second argument is
  the element of `xs`. `f` returns a value that will be used as the initial value of the next call to `f`. The final
  value of `f` is returned.

## `reduce2`

```phel
(reduce2 f [x & xs])
```
The 2-argument version of reduce that does not take a initialization value.
  Instead the first argument of the list is used as initialization value.

## `remove`

```phel
(remove xs offset & [n])
```
Removes up to `n` element from array `xs` starting at index `offset`.

## `repeat`

```phel
(repeat n x)
```
Returns an array of length n where every element is x.

## `rest`

```phel
(rest xs)
```
Returns the sequence of elements after the first element. If there are no
elements, returns an empty sequence.

## `reverse`

```phel
(reverse xs)
```
Reverses the order of the elements in the given sequence.

## `second`

```phel
(second xs)
```
Returns the second element of an indexed sequence or nil.

## `set`

```phel
(set & xs)
```
Creates a new Set. If no argument is provided, an empty Set is created.

## `set-meta!`

```phel
(set-meta! obj)
```
Sets the meta data to a given object

## `set?`

```phel
(set? x)
```
Returns true if `x` is a set, false otherwise.

## `shuffle`

```phel
(shuffle xs)
```
Returns a random permutation of xs.

## `slice`

```phel
(slice xs & [offset & [length]])
```
Extract a slice of `xs`.

## `some?`

```phel
(some? pred xs)
```
Returns true if `(pred x)` is logical true for at least one `x` in `xs`, else false.

## `sort`

```phel
(sort xs & [comp])
```
Returns a sorted array. If no comparator is supplied compare is used.

## `sort-by`

```phel
(sort-by keyfn xs & [comp])
```
Returns a sorted array where the sort order is determined by comparing
  (keyfn item). If no comparator is supplied compare is used.

## `split-at`

```phel
(split-at n xs)
```
Returns a vector of [(take n coll) (drop n coll)].

## `split-with`

```phel
(split-with f xs)
```
Returns a vector of [(take-while pred coll) (drop-while pred coll)].

## `str`

```phel
(str & args)
```
Creates a string by concatenating values together. If no arguments are
provided an empty string is returned. Nil and false are represented as empty
string. True is represented as 1. Otherwise it tries to call `__toString`.
This is PHP equivalent to `$args[0] . $args[1] . $args[2] ...`

## `str-contains?`

```phel
(str-contains? str s)
```
True if str contains s.

## `string?`

```phel
(string? x)
```
Returns true if `x` is a string, false otherwise.

## `struct?`

```phel
(struct? x)
```
Returns true if `x` is a struct, false otherwise.

## `sum`

```phel
(sum xs)
```
Returns the sum of all elements is `xs`.

## `symbol?`

```phel
(symbol? x)
```
Returns true if `x` is a symbol, false otherwise.

## `symmetric-difference`

```phel
(symmetric-difference set & sets)
```
Symmetric difference between multiple sets into a new one.

## `table`

```phel
(table & xs)
```
Creates a new Table. If no argument is provided, an empty Table is created.
The number of parameters must be even.

## `table?`

```phel
(table? x)
```
Returns true if `x` is a table, false otherwise.

## `take`

```phel
(take n xs)
```
Takes the first `n` elements of `xs`.

## `take-last`

```phel
(take-last n xs)
```
Takes the last `n` elements of `xs`.

## `take-while`

```phel
(take-while pred xs)
```
Takes alle elements at the front of `xs` where `(pred x)` is true.

## `test/deftest`

```phel
(deftest name & body)
```
Defines a test function with no arguments

## `test/is`

```phel
(is form & [message])
```
Generic assertion macro.

## `test/print-summary`

```phel
(print-summary )
```
Prints the summary of the test suite

## `test/report`

```phel
(report data)
```


## `test/run-tests`

```phel
(run-tests & namespaces)
```
Runs all test functions in the given namespaces

## `test/successful?`

```phel
(successful? )
```
Checks if all tests have passed.

## `to-php-array`

```phel
(to-php-array xs)
```
Create a PHP Array from a sequential data structure.

## `transient`

```phel
(transient coll)
```
Converts a persistent collection to a transient collection

## `tree-seq`

```phel
(tree-seq branch? children root)
```
Returns an array of the nodes in the tree, via a depth first walk.
  branch? is a function with one argument that returns true if the given node
  has children.
  children must be a function with one argument that returns the children of the node.
  root the root node of the tree.

## `true?`

```phel
(true? x)
```
Checks if `x` is true. Same as `x === true` in PHP.

## `truthy?`

```phel
(truthy? x)
```
Checks if `x` is truthy. Same as `x == true` in PHP.

## `type`

```phel
(type x)
```
Returns the type of `x`. Following types can be returned:

* `:vector`
* `:list`
* `:struct`
* `:hash-map`
* `:set`
* `:array`
* `:table`
* `:keyword`
* `:symbol`
* `:int`
* `:float`
* `:string`
* `:nil`
* `:boolean`
* `:function`
* `:php/array`
* `:php/resource`
* `:php/object`
* `:unknown`

## `union`

```phel
(union & sets)
```
Union multiple sets into a new one.

## `unset`

```phel
(unset ds key)
```
Returns `ds` without `key`.

## `update`

```phel
(update ds k f & args)
```
Updates a value in a datastructure by applying `f` to the current element and replacing it with the result of `f`.

## `update-in`

```phel
(update-in ds [k & ks] f & args)
```
Updates a value into a nested data structure.

## `values`

```phel
(values xs)
```
Gets the values of an associative data structure.

## `vector`

```phel
(vetcor & xs)
```
Creates a new vector. If no argument is provided, an empty list is created.

## `vector?`

```phel
(vector? x)
```
Returns true if `x` is a vector, false otherwise.

## `when`

```phel
(when test & body)
```
Evaluates `test` and if that is logical true, evaluates `body`.

## `when-not`

```phel
(when-not test & body)
```
Evaluates `test` and if that is logical false, evaluates `body`.

## `with-output-buffer`

```phel
(with-output-buffer & body)
```
Everything that is printed inside the body will be stored in a buffer.
   The result of the buffer is returned.

## `zero?`

```phel
(zero? x)
```
Checks if `x` is zero.

## `zipcoll`

```phel
(zipcoll a b)
```
Creates a table from two sequential data structures. Return a new table.

