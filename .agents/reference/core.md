# Phel Core Reference

Auto-generated from `:doc` / `:example` / `:see-also` metadata on public
`defn` forms in `src/phel/core/*.phel`. Do not edit by hand.

- Run `composer docs-agents-reference` to regenerate.
- `composer test-agents` fails when this file drifts from the source.

## `%`

```phel
(% dividend divisor)
```

Return the remainder of `dividend` / `divisor`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L148)

## `*`

```phel
(* & xs)
```

Returns the product of all elements in `xs`. All elements in `xs` must be
numbers. If `xs` is empty, return 1.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L122)

## `**`

```phel
(** a x)
```

Return `a` to the power of `x`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L155)

## `*assert*`

Controls whether `assert` expands to a runtime check. When logical
  false at macroexpansion time, `assert` expands to nil and performs no
  runtime check, matching Clojure's compile-time `*assert*` semantics.
  Defaults to `true`. To disable globally, set the core binding before
  compilation via PHP: `\Phel::addDefinition("phel\\core", "*assert*", false)`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L17)

## `*build-mode*`

## `*file*`

```phel
*file*
```

Returns the path of the current source file.

**Example**

```phel
(println *file*) ; => "/path/to/current/file.phel"
```

## `*hierarchy*`

Global hierarchy for keyword/symbol taxonomies.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L47)

## `*ns*`

```phel
*ns*
```

Returns the namespace in the current scope.

**Example**

```phel
(println *ns*) ; => "my-app\core"
```

## `*program*`

The script path or namespace being executed.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L145)

## `+`

```phel
(+ & xs)
```

Returns the sum of all elements in `xs`. All elements `xs` must be numbers.
  If `xs` is empty, return 0.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L98)

## `-`

```phel
(- & xs)
```

Returns the difference of all elements in `xs`. If `xs` is empty, return 0. If `xs`
  has one element, return the negative value of that element.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L109)

## `->`

```phel
(-> x & forms)
```

Threads the expr through the forms. Inserts `x` as the second item
  in the first form, making a list of it if it is not a list already.
  If there are more forms, insert the first form as the second item in
  the second form, etc.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L184)

## `->>`

```phel
(->> x & forms)
```

Threads the expr through the forms. Inserts `x` as the
  last item in the first form, making a list of it if it is not a
  list already. If there are more forms, insert the first form as the
  last item in the second form, etc.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L200)

## `/`

```phel
(/ & xs)
```

Returns the nominator divided by all the denominators. If `xs` is empty,
returns 1. If `xs` has one value, returns the reciprocal of x.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L135)

## `<`

```phel
(< a & more)
```

Checks if each argument is strictly less than the following argument.

**Example**

```phel
(< 1 2 3 4) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L125)

## `<=`

```phel
(<= a & more)
```

Checks if each argument is less than or equal to the following argument. Returns a boolean.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L138)

## `<=>`

```phel
(<=> a b)
```

Alias for the spaceship PHP operator in ascending order. Returns an int.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L175)

## `=`

```phel
(= a & more)
```

Checks if all values are equal (value equality, not identity).

**Example**

```phel
(= [1 2 3] [1 2 3]) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L100)

## `>`

```phel
(> a & more)
```

Checks if each argument is strictly greater than the following argument.

**Example**

```phel
(> 4 3 2 1) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L150)

## `>=`

```phel
(>= a & more)
```

Checks if each argument is greater than or equal to the following argument. Returns a boolean.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L163)

## `>=<`

```phel
(>=< a b)
```

Alias for the spaceship PHP operator in descending order. Returns an int.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L181)

## `NAN`

Constant for Not a Number (NAN) values.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L94)

## `NaN?`

```phel
(NaN? x)
```

Checks if `x` is not a number. Alias for `nan?`, matching Clojure's `NaN?`.

**See also:** `nan?`, `inf?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L211)

## `abs`

```phel
(abs x)
```

Returns the absolute value of `x`.
  Throws `InvalidArgumentException` if `x` is not a number, matching Clojure
  rather than PHP's permissive `abs(null) => 0` / `abs("abc")` coercions.

**Example**

```phel
(abs -5) ; => 5
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L224)

## `aclone`

```phel
(aclone arr)
```

Returns a shallow copy of a PHP array. The returned array is a
  distinct value — mutating the copy via `php/aset` does not affect the
  original, and vice versa. Matches Clojure's `aclone` for `.cljc`
  interop; raises `InvalidArgumentException` on non-array inputs since
  Phel's persistent collections are already immutable and don't need
  cloning.

**Example**

```phel
(aclone (object-array 3)) ; => a fresh PHP array [nil, nil, nil]
```

**See also:** `object-array`, `to-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L79)

## `add-tap`

```phel
(add-tap f)
```

Registers `f` as a tap. Every call to `tap>` invokes each registered tap
  with the tapped value. Returns nil.

**Example**

```phel
(add-tap println)
(tap> 42) ; prints 42
```

**See also:** `remove-tap`, `tap>`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/tap.phel#L15)

## `add-watch`

```phel
(add-watch variable key f)
```

Adds a watch function to a variable. The watch fn is called when the variable
  changes with four arguments: key, ref, old-value, new-value.

**Example**

```phel
(add-watch my-var :logger (fn [key ref old new] (println old "->" new)))
```

**See also:** `remove-watch`, `var`, `swap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L101)

## `aget`

```phel
(aget arr & indices)
```

Returns the value at `index` in a PHP array. With multiple indices,
   accesses nested arrays: `(aget arr i j)` is `(aget (aget arr i) j)`.
   Matches Clojure's `aget` for `.cljc` interop.

**Example**

```phel
(aget (php/array 10 20 30) 1) ; => 20
```

**See also:** `aset`, `aclone`, `object-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L160)

## `alength`

```phel
(alength arr)
```

Returns the number of elements in a PHP array. Matches Clojure's
  `alength` for `.cljc` interop; raises `InvalidArgumentException` on
  non-array inputs (use `count` for collections).

**Example**

```phel
(alength (int-array 3)) ; => 3
```

**See also:** `aget`, `aset`, `count`, `object-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L91)

## `all?`

```phel
(all? pred coll)
```

Returns true if predicate is true for every element in collection, false otherwise.

**Example**

```phel
(all? even? [2 4 6 8]) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L189)

## `alter-var-root`

```phel
(alter-var-root & _)
```

Clojure's `alter-var-root` is out of scope in Phel: there are no first-class
  vars whose root binding could be swapped. Reaching for this function in
  `.cljc` code is nearly always a bug; prefer an `atom` and `swap!` for mutable
  state, or redefine the top-level binding with `def` if the intent was to
  replace it at load time. Calling `alter-var-root` at runtime throws to make
  the mismatch obvious instead of silently no-oping.

**See also:** `swap!`, `atom`, `def`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L110)

## `ancestors`

```phel
(ancestors tag)
```

Returns the set of all transitive ancestors of tag, or nil.

**See also:** `parents`, `descendants`, `derive`, `isa?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L137)

## `and`

```phel
(and & args)
```

Evaluates expressions left to right, returning the first falsy value or the last value.

**Example**

```phel
(and true 1 "hello") ; => "hello"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L45)

## `any?`

```phel
(any? _)
```

Returns true given any argument, including `nil` and `false`. Mirrors
  Clojure's `clojure.core/any?` — useful as a default predicate in spec
  / validation contexts where every value should be accepted.

**Example**

```phel
(any? nil) ; => true
(any? 0) ; => true
```

**See also:** `some?`, `every?`, `ifn?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L267)

## `apply`

```phel
(apply f expr*)
```

Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.

**Example**

```phel
(apply + [1 2 3]) ; => 6
```

## `argv`

Vector of user arguments passed to the script (excludes program name).
  Use *program* to get the script path or namespace.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L150)

## `array-map`

Constructs a map from the given key/value pairs. If any keys are
  equal, later values replace earlier ones, as if by repeated `assoc`.
  Phel has no distinct array-map type, so the result is the same
  persistent map as `hash-map` — `array-map` exists for `.cljc` interop
  with Clojure sources.

**Example**

```phel
(array-map :a 1 :b 2) ; => {:a 1 :b 2}
```

**See also:** `hash-map`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L47)

## `as->`

```phel
(as-> expr name & forms)
```

Binds `name` to `expr`, evaluates the first form in the lexical context
  of that binding, then binds name to that result, repeating for each
  successive form, returning the result of the last form.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L264)

## `aset`

```phel
(aset arr idx & more)
```

Sets the value at `index` in a PHP array to `val`. Returns `val`.
   With additional indices, navigates nested arrays before setting:
   `(aset arr i j val)` sets index `j` in `(aget arr i)`.
   Matches Clojure's `aset` for `.cljc` interop.
   This is a macro because PHP arrays are value types; a function
   wrapper would mutate a copy rather than the original.

**Example**

```phel
(let [a (php/array 1 2 3)] (aset a 0 42) (aget a 0)) ; => 42
```

**See also:** `aget`, `aclone`, `object-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L173)

## `assert`

```phel
(assert expr & [message])
```

Throws an exception if expr is falsy. Optional message string.
  Used for precondition checking in application code. When `*assert*`
  is logical false at macroexpansion time, `assert` expands to nil and
  performs no runtime check.

**See also:** `when`, `throw`, `*assert*`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L684)

## `assoc`

```phel
(assoc ds key value & more)
```

Associates one or more key-value pairs with a collection.
  Additional key-value pairs beyond the first are applied in order.
  Throws if an odd number of extra arguments is provided.

**Example**

```phel
(assoc {:a 1} :b 2) ; => {:a 1 :b 2}
(assoc {:a 1} :b 2 :c 3) ; => {:a 1 :b 2 :c 3}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L186)

## `assoc!`

```phel
(assoc! tcoll key value & more)
```

Associates one or more key-value pairs with a transient collection,
   mutating it in place. Works on transient hash-maps and transient vectors.
   Variadic forms apply each `key-value` pair in order. Raises
   `InvalidArgumentException` when `tcoll` is not a supported transient
   collection or when an odd number of extra arguments is provided.
   Matches Clojure's `assoc!` semantics.

**Example**

```phel
(persistent! (assoc! (transient {}) :a 1 :b 2)) ; => {:a 1 :b 2}
```

**See also:** `assoc`, `conj!`, `transient`, `persistent!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L108)

## `assoc-in`

```phel
(assoc-in ds [k & ks] v)
```

Associates a value in a nested data structure at the given path.

  Creates intermediate maps if they don't exist.

**Example**

```phel
(assoc-in {:a {:b 1}} [:a :c] 2) ; => {:a {:b 1 :c 2}}
```

**See also:** `get-in`, `update-in`, `dissoc-in`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L266)

## `associative?`

```phel
(associative? x)
```

Returns true if `x` is an associative data structure, false otherwise.

  Associative data structures include vectors, hash maps, structs, and PHP arrays
  (both indexed and associative), matching Clojure's `Associative` protocol.

**Example**

```phel
(associative? [1 2 3]) ; => true
```

**See also:** `vector?`, `map?`, `hash-map?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L382)

## `atom`

```phel
(atom value)
```

Creates a new atom with the given value.

  Atoms provide a way to manage mutable state. Use `reset!` to set a new value
  and `swap!` to update based on the current value.

**Example**

```phel
(def counter (atom 0))
```

**See also:** `reset!`, `deref`, `swap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L20)

## `atom?`

```phel
(atom? x)
```

Returns true if the given value is an atom.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L39)

## `binding`

```phel
(binding bindings & body)
```

Temporary redefines definitions while executing the body.
  The value will be reset after the body was executed.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L383)

## `bit-and`

```phel
(bit-and x y & args)
```

Bitwise and.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L25)

## `bit-clear`

```phel
(bit-clear x n)
```

Clear bit an index `n`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L75)

## `bit-flip`

```phel
(bit-flip x n)
```

Flip bit at index `n`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L80)

## `bit-not`

```phel
(bit-not x)
```

Bitwise complement.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L49)

## `bit-or`

```phel
(bit-or x y & args)
```

Bitwise or.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L33)

## `bit-set`

```phel
(bit-set x n)
```

Set bit an index `n`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L70)

## `bit-shift-left`

```phel
(bit-shift-left x n)
```

Bitwise shift left.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L56)

## `bit-shift-right`

```phel
(bit-shift-right x n)
```

Bitwise shift right.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L63)

## `bit-test`

```phel
(bit-test x n)
```

Test bit at index `n`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L85)

## `bit-xor`

```phel
(bit-xor x y & args)
```

Bitwise xor.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L41)

## `boolean`

```phel
(boolean x)
```

Coerces `x` to a boolean. Returns `false` if `x` is `nil` or `false`,
   `true` otherwise.

**Example**

```phel
(boolean nil) ; => false
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L260)

## `boolean?`

```phel
(boolean? x)
```

Returns true if `x` is a boolean, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L255)

## `butlast`

```phel
(butlast coll)
```

Returns all but the last item in `coll`.

**Example**

```phel
(butlast [1 2 3 4]) ; => [1 2 3]
```

**See also:** `last`, `drop-last`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L363)

## `byte`

```phel
(byte x)
```

Coerces `x` to a signed 8-bit integer in the range `-128..127`.
   Decimal values are truncated toward zero (as in Clojure on the JVM).
   Values outside the range or non-numeric inputs raise
   `InvalidArgumentException`. Phel has no dedicated byte type, so the
   result is a plain PHP int — `byte` exists for `.cljc` interop with
   Clojure sources.

**Example**

```phel
(byte 127) ; => 127
(byte 1.9) ; => 1
(byte -128) ; => -128
```

**See also:** `int?`, `float`, `double`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L285)

## `case`

```phel
(case e & pairs)
```

Evaluates expression and matches it against constant test values, returning the associated result.

**Example**

```phel
(case x 1 "one" 2 "two" "other") ; => "one" (when x is 1)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L45)

## `cat`

```phel
(cat rf)
```

A transducer that concatenates the contents of each input into the reduction.

**See also:** `mapcat`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L842)

## `catch`

```phel
(catch exception-type exception-name expr*)
```

Handle exceptions thrown in a `try` block by matching on the provided exception type. The caught exception is bound to exception-name while evaluating the expressions.

**Example**

```phel
(try (throw (php/new \Exception "error")) (catch \Exception e (php/-> e (getMessage))))
```

## `char`

```phel
(char x)
```

Coerces `x` to a single-character string representing the given
   Unicode code point. Accepts a non-negative integer (the code point,
   converted via `mb_chr`) or a single-character string, which is
   returned as-is. Phel has no dedicated char type — character literals
   such as `\A` are already single-character strings — so the result
   is always a plain string. Matches Clojure's `char` for `.cljc`
   interop; raises `InvalidArgumentException` on negative ints,
   non-single-character strings, and all other inputs.

**Example**

```phel
(char 65) ; => "A"
(char 32) ; => " "
(char \A) ; => "A"
```

**See also:** `byte`, `int?`, `string?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L303)

## `char?`

```phel
(char? x)
```

Returns true if `x` is a single-character string, false otherwise.
   Phel has no dedicated character type — character literals such as
   `\A` are already single-character strings — so `char?` is true for
   any string of length 1 (UTF-8 counted). Matches ClojureScript's
   `char?` for `.cljc` interop; Clojure/JVM's `char?` tests for the
   distinct `Character` type, which does not exist here.

**Example**

```phel
(char? \A) ; => true
(char? "a") ; => true
(char? "ab") ; => false
```

**See also:** `char`, `string?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L142)

## `coerce-in`

```phel
(coerce-in v min max)
```

Returns `v` if it is in the range, or `min` if `v` is less than `min`, or `max` if `v` is greater than `max`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L380)

## `coll?`

```phel
(coll? x)
```

Returns true if `x` is a persistent collection — vector, list, hash-map
   (including sorted-map), struct, set (including sorted-set), or lazy-seq —
   and false otherwise. Strings, numbers, `nil`, booleans, keywords, symbols,
   and plain PHP arrays are not considered collections, matching Clojure's
   `IPersistentCollection` membership.

**Example**

```phel
(coll? [1 2 3]) ; => true
(coll? "abc") ; => false
```

**See also:** `vector?`, `map?`, `list?`, `set?`, `seq?`, `sequential?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L406)

## `comment`

```phel
(comment &)
```

Ignores the body of the comment.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L125)

## `comp`

```phel
(comp & fs)
```

Takes a list of functions and returns a function that is the composition of those functions.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L77)

## `compact`

```phel
(compact coll & values)
```

Returns a lazy sequence with specified values removed from `coll`.
  If no values are specified, removes nil values by default.

**Example**

```phel
(compact [1 nil 2 nil 3]) ; => (1 2 3)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1108)

## `compare`

```phel
(compare x y)
```

Compares `x` and `y` using PHP's spaceship operator, returning a negative
  integer, zero, or a positive integer when `x` is less than, equal to, or
  greater than `y`.

  `nil` is less than every non-nil value and equal to itself. Throws
  `InvalidArgumentException` when `x` and `y` come from mutually incomparable
  categories (e.g. `(compare 1 [])`).

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L303)

## `compile`

```phel
(compile form)
```

Returns the compiled PHP code string for the given form.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L728)

## `complement`

```phel
(complement f)
```

Returns a function that takes the same arguments as `f` and returns the opposite truth value.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L91)

## `completing`

```phel
(completing f & args)
```

Takes a reducing function `f` of 2 args and returns a fn suitable for transduce
  by adding a 1-arity (completion) that calls `cf` (default: identity).

**See also:** `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L74)

## `concat`

```phel
(concat & colls)
```

Concatenates multiple collections into a lazy sequence.

**Example**

```phel
(concat [1 2] [3 4]) ; => (1 2 3 4)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L828)

## `cond`

```phel
(cond & pairs)
```

Evaluates test/expression pairs, returning the first matching expression.

**Example**

```phel
(cond (< x 0) "negative" (> x 0) "positive" "zero") ; => "negative", "positive", or "zero" depending on x
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L31)

## `cond->`

```phel
(cond-> expr & clauses)
```

Takes an expression and a set of test/form pairs. Threads `expr` (via `->`)
  through each form for which the corresponding test expression is true.
  Note that, unlike `cond` branching, `cond->` threading does not short-circuit
  after the first true test expression.

**Example**

```phel
(cond-> 1 true inc false (* 42) true (* 3)) ; => 6
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L288)

## `cond->>`

```phel
(cond->> expr & clauses)
```

Takes an expression and a set of test/form pairs. Threads `expr` (via `->>`)
  through each form for which the corresponding test expression is true.
  Note that, unlike `cond` branching, `cond->>` threading does not short-circuit
  after the first true test expression.

**Example**

```phel
(cond->> [1 2 3] true (map inc) false (filter odd?)) ; => [2 3 4]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L305)

## `condp`

```phel
(condp pred expr & clauses)
```

Takes a binary predicate, an expression, and a set of clauses.
  Each clause takes the form of either:
    test-expr result-expr
    test-expr :>> result-fn
  For each clause, (pred test-expr expr) is evaluated. If it returns
  logical true, the clause is a match. If a binary clause is a match,
  result-expr is returned. If a ternary clause with :>> is a match,
  the result of (pred test-expr expr) is passed to result-fn and the
  return value is the result. If no clause matches, the default value
  is returned (if provided), otherwise an exception is thrown.

**Example**

```phel
(condp = 1 1 "one" 2 "two" "other") ; => "one"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L76)

## `conj`

```phel
(conj)
```

```phel
(conj coll)
```

```phel
(conj coll value)
```

```phel
(conj coll value & more)
```

Returns a new collection with values added. Appends to vectors/sets, prepends to lists.

**Example**

```phel
(conj [1 2] 3) ; => [1 2 3]
```

## `conj!`

```phel
(conj!)
```

```phel
(conj! tcoll)
```

```phel
(conj! tcoll value)
```

```phel
(conj! tcoll value & more)
```

Adds `value` to the transient collection `tcoll`, mutating it in place,
   and returns `tcoll`. The 'addition' may happen at different 'places'
   depending on the concrete transient type: transient vectors append at
   the tail, transient hash-sets add the element (no-op if already
   present), and transient hash-maps treat `value` as a `[key value]`
   pair (or an associative collection of entries).
   With zero arguments returns a new empty transient vector. With one
   argument returns `tcoll` unchanged. Variadic forms reduce `conj!` over
   the remaining values. Raises `InvalidArgumentException` when `tcoll`
   is not a transient collection. Matches Clojure's `conj!` semantics.

**Example**

```phel
(persistent (conj! (transient [1 2]) 3)) ; => [1 2 3]
```

**See also:** `conj`, `transient`, `persistent`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L76)

## `cons`

```phel
(cons x coll)
```

Prepends an element to the beginning of a collection.

**Example**

```phel
(cons 0 [1 2 3]) ; => [0 1 2 3]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L26)

## `constantly`

```phel
(constantly x)
```

Returns a function that always returns `x` and ignores any passed arguments.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L86)

## `contains-value?`

```phel
(contains-value? coll val)
```

Returns true if the value is present in the given collection, otherwise returns false.

**Example**

```phel
(contains-value? {:a 1 :b 2} 2) ; => true
```

**See also:** `contains?`, `some?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L735)

## `contains?`

```phel
(contains? coll key)
```

Returns true if key is present in collection (checks keys/indices, not values).

**Example**

```phel
(contains? [10 20 30] 1) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L275)

## `count`

```phel
(count coll)
```

Counts the number of elements in a sequence. Can be used on everything that implements the PHP Countable interface.

  Works with lists, vectors, hash-maps, sets, strings, and PHP arrays.
  Returns 0 for nil.

**Example**

```phel
(count [1 2 3]) ; => 3
```

**See also:** `empty?`, `seq`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L81)

## `counted?`

```phel
(counted? coll)
```

Returns true if `coll` can report its length in constant time — persistent
   vectors, lists, hash-maps (including sorted-map), structs, and sets
   (including sorted-set). Returns false for lazy sequences (counting them
   requires realizing the whole sequence), strings, numbers, `nil`, and every
   other non-counted type. Matches Clojure's `counted?` semantics, which
   mirror the `clojure.lang.Counted` marker interface.

**Example**

```phel
(counted? [1 2 3]) ; => true
(counted? (range)) ; => false
```

**See also:** `count`, `coll?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L433)

## `csv-seq`

```phel
(csv-seq filename)
```

```phel
(csv-seq filename options)
```

Returns a lazy sequence of rows from a CSV file.

**Example**

```phel
(take 10 (csv-seq "data.csv")) ; => [["col1" "col2"] ["val1" "val2"] ...]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L163)

## `cycle`

```phel
(cycle coll)
```

Returns an infinite lazy sequence that cycles through the elements of collection.

**Example**

```phel
(take 7 (cycle [1 2 3])) ; => (1 2 3 1 2 3 1)
```

**See also:** `iterate`, `repeat`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L819)

## `dec`

```phel
(dec x)
```

Decrements `x` by one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L169)

## `declare`

Declare a global symbol before it is defined.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L135)

## `dedupe`

```phel
(dedupe & args)
```

Returns a lazy sequence with consecutive duplicate values removed in `coll`.
  When called with no args, returns a transducer.

**Example**

```phel
(dedupe [1 1 2 2 2 3 1 1]) ; => (1 2 3 1)
```

**See also:** `distinct`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1081)

## `deep-merge`

```phel
(deep-merge & args)
```

Recursively merges data structures.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L270)

## `def`

```phel
(def name meta? value)
```

This special form binds a value to a global symbol.

**Example**

```phel
(def my-value 42)
```

## `def-`

Define a private value that will not be exported.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L79)

## `defexception`

```phel
(defexception name)
```

Define a new exception.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L111)

## `defexception*`

```phel
(defexception name)
```

Defines a new exception.

**Example**

```phel
(defexception my-error)
```

## `definterface`

```phel
(definterface name & fns)
```

An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.

**Example**

```phel
(definterface name & fns)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L24)

## `definterface*`

```phel
(definterface name & fns)
```

An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.

**Example**

```phel
(definterface Greeter (greet [name]))
```

## `defmacro`

Define a macro.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L84)

## `defmacro-`

```phel
(defmacro- name & fdecl)
```

Define a private macro that will not be exported.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L94)

## `defmethod`

```phel
(defmethod multi-name dispatch-val & fn-tail)
```

Registers a method implementation for a multimethod.

  `multi-name` is the name of the multimethod defined by `defmulti`.
  When extending a multimethod from a different namespace, fully qualify
  the multi-name (e.g. `phel\test/assert-expr`) so the methods table is
  resolved in the multimethod's home namespace.
  `dispatch-val` is the value that triggers this method.
  `args` and `body` define the function implementation.

**Example**

```phel
(defmethod area :circle [{:radius r}] (* 3.14159 r r))
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L537)

## `defmulti`

```phel
(defmulti name dispatch-fn)
```

Defines a multimethod. `dispatch-fn` is called on the arguments to
  produce a dispatch value, which is then used to select the appropriate
  method registered via `defmethod`.

  If no method matches the dispatch value, the `:default` method is used
  (if defined), otherwise an error is thrown.

**Example**

```phel
(defmulti area :shape)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L508)

## `defn`

Define a new global function.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L74)

## `defn-`

```phel
(defn- name & fdecl)
```

Define a private function that will not be exported.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L89)

## `defprotocol`

```phel
(defprotocol protocol-name & method-specs)
```

Defines a protocol with the given method signatures. Each method signature
  is a list of (method-name [args]).

  Creates a dispatching function for each method that dispatches on the type
  of the first argument. Use `extend-type` to add implementations.

  A `:default` type can be registered via `extend-type` as a fallback when
  no specific type implementation is found.

**Example**

```phel
(defprotocol Stringable (to-string [this]))
```

**See also:** `extend-type`, `satisfies?`, `extends?`, `protocol-type-key`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L212)

## `defrecord`

```phel
(defrecord name fields & impls)
```

Defines a record type with the given fields, matching Clojure's `defrecord`.

  Expands to a `defstruct` plus Clojure-style factory functions:
  - `Name` — positional constructor (from `defstruct`)
  - `Name?` — type predicate (from `defstruct`)
  - `->Name` — positional factory, identical to `Name`
  - `map->Name` — map factory that takes `{:field value ...}`

  An optional tail of protocol/method forms is spliced into an `extend-type`
  call, so inline protocol implementations work exactly like Clojure's
  `defrecord` body. Only Phel protocols are supported in the inline tail;
  PHP interface implementations remain on `defstruct`.

**Example**

```phel
(defrecord Point [x y] Drawable (draw [this canvas] ...))
```

**See also:** `deftype`, `defstruct`, `defprotocol`, `extend-type`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L445)

## `defstruct`

```phel
(defstruct name keys & implementations)
```

A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L99)

## `defstruct*`

```phel
(defstruct name [keys*])
```

A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.

**Example**

```phel
(defstruct point [x y])
```

## `deftype`

```phel
(deftype name fields & impls)
```

Defines a type with the given fields, matching Clojure's `deftype` syntax.

  Expands to a `defstruct` plus a Clojure-style positional factory:
  - `Name` — positional constructor (from `defstruct`)
  - `Name?` — type predicate (from `defstruct`)
  - `->Name` — positional factory, identical to `Name`

  Unlike `defrecord`, no `map->Name` factory is generated.

  An optional tail of protocol/method forms is spliced into an `extend-type`
  call. Only Phel protocols are supported in the inline tail.

  Deviation from Clojure: Phel's `deftype` shares the map-backed
  `defstruct` infrastructure, so instances remain map-like (keys are
  accessible via `get`). Clojure's `deftype` produces a non-map type;
  if you need that semantic, use native PHP interop.

**Example**

```phel
(deftype PointT [x y] Drawable (draw [this canvas] ...))
```

**See also:** `defrecord`, `defstruct`, `defprotocol`, `extend-type`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L475)

## `delay`

```phel
(delay & body)
```

Takes a body of expressions and yields a Delay object that will invoke the
  body only the first time it is forced (via force or deref/@), caching the result.

**Example**

```phel
(def d (delay (println "computing") 42))
```

**See also:** `force`, `delay?`, `realized?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/lazy.phel#L12)

## `delay?`

```phel
(delay? x)
```

Returns true if x is a Delay.

**See also:** `delay`, `force`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/lazy.phel#L29)

## `deref`

```phel
(deref variable & args)
```

Returns the current value inside the variable.

  With three arguments, and when `variable` is a `PhelFuture`, blocks for
  at most `timeout-ms` milliseconds waiting for the future to resolve. If
  the future has not completed within the timeout, returns `timeout-val`.
  The 3-arg form is only supported on `PhelFuture`.

**Example**

```phel
(deref (atom 42)) ; => 42
```

**See also:** `atom`, `reset!`, `swap!`, `future`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L67)

## `derive`

```phel
(derive child parent)
```

Establishes a parent/child relationship between child and parent keywords
  in the global hierarchy. Throws on cyclic derivation.

**Example**

```phel
(derive :square :shape)
```

**See also:** `underive`, `isa?`, `parents`, `ancestors`, `descendants`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L97)

## `descendants`

```phel
(descendants tag)
```

Returns the set of all descendants of tag, or nil.

**See also:** `parents`, `ancestors`, `derive`, `isa?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L144)

## `difference`

```phel
(difference set & sets)
```

Difference between multiple sets into a new one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L54)

## `disj`

```phel
(disj set)
```

```phel
(disj set k)
```

```phel
(disj set k & ks)
```

Returns a new set that does not contain the given key(s). Works on hash-sets and sorted-sets.
  Removing a non-existent key is a no-op. Returns `nil` when called on `nil`.

**Example**

```phel
(disj #{1 2 3} 2) ; => #{1 3}
```

**See also:** `conj`, `hash-set`, `sorted-set`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L207)

## `disj!`

```phel
(disj! tcoll)
```

```phel
(disj! tcoll value)
```

```phel
(disj! tcoll value & more)
```

Removes one or more elements from a transient set, mutating it in place.
   Raises `InvalidArgumentException` when `tcoll` is not a transient set.
   Matches Clojure's `disj!` semantics.

**Example**

```phel
(persistent! (disj! (transient #{1 2 3}) 2)) ; => #{1 3}
```

**See also:** `disj`, `conj!`, `transient`, `persistent!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L160)

## `dissoc`

```phel
(dissoc ds & ks)
```

Returns `ds` without the given keys. With no keys returns `ds` unchanged.

**Example**

```phel
(dissoc {:a 1 :b 2} :b) ; => {:a 1}
(dissoc {:a 1 :b 2 :c 3} :a :c) ; => {:b 2}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L226)

## `dissoc!`

```phel
(dissoc! tcoll)
```

```phel
(dissoc! tcoll key)
```

```phel
(dissoc! tcoll key & ks)
```

Dissociates one or more keys from a transient map, mutating it in place.
   Raises `InvalidArgumentException` when `tcoll` is not a transient map.
   Matches Clojure's `dissoc!` semantics.

**Example**

```phel
(persistent! (dissoc! (transient {:a 1 :b 2}) :a)) ; => {:b 2}
```

**See also:** `dissoc`, `assoc!`, `transient`, `persistent!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L139)

## `dissoc-in`

```phel
(dissoc-in ds [k & ks])
```

Dissociates a value from a nested data structure at the given path.

**Example**

```phel
(dissoc-in {:a {:b 1 :c 2}} [:a :b]) ; => {:a {:c 2}}
```

**See also:** `dissoc`, `assoc-in`, `get-in`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L301)

## `distinct`

```phel
(distinct & args)
```

Returns a lazy sequence with duplicated values removed in `coll`.
  When called with no args, returns a transducer.

**Example**

```phel
(distinct [1 2 1 3 2 4 3]) ; => (1 2 3 4)
```

**See also:** `frequencies`, `set`, `dedupe`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L545)

## `do`

```phel
(do expr*)
```

Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.

**Example**

```phel
(do (println "Hello") (+ 1 2)) ; prints "Hello", returns 3
```

## `doall`

```phel
(doall coll)
```

Forces realization of a lazy sequence and returns it as a vector.

**Example**

```phel
(doall (map println [1 2 3])) ; => [nil nil nil]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L937)

## `dofor`

```phel
(dofor head & body)
```

Repeatedly executes body for side effects with bindings and modifiers as
  provided by for. Returns nil.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L261)

## `dorun`

```phel
(dorun coll)
```

Forces realization of a lazy sequence for side effects, returns nil.

**Example**

```phel
(dorun (map println [1 2 3])) ; => nil
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L948)

## `doseq`

```phel
(doseq seq-exprs & body)
```

Repeatedly executes body for side effects with Clojure-style bindings.
  `(doseq [x coll] body)` runs `body` once per element of `coll`. When `coll`
  is a map, each iteration sees a `[k v]` entry pair, so destructuring works
  just like in Clojure: `(doseq [[k v] m] ...)`. Supports `:when`, `:while`,
  and `:let` modifiers between bindings.

**Example**

```phel
(doseq [x [1 2 3]] (println x))
(doseq [[k v] {:a 1 :b 2}] (println k v))
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L315)

## `doseq-iterable`

```phel
(doseq-iterable coll)
```

Internal helper used by the `doseq` macro expansion. Returns a value
  suitable for Clojure-style iteration: maps are expanded to a sequence of
  `[k v]` pair vectors so destructuring binds entries as in Clojure. Every
  other value is returned unchanged.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L274)

## `dotimes`

```phel
(dotimes [binding n] & body)
```

Evaluates body `n` times with `binding` bound to integers from 0 to n-1.
  Returns nil.

**Example**

```phel
(dotimes [i 5] (println i))
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/loops.phel#L17)

## `doto`

```phel
(doto x & forms)
```

Evaluates x then calls all of the methods and functions with the
  value of x supplied at the front of the given arguments. The forms
  are evaluated in order. Returns x.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L274)

## `double`

```phel
(double x)
```

Coerces `x` to a double. In PHP there is no distinction between float and
   double; both map to the same native PHP float type. Alias for `float`.

**Example**

```phel
(double 1) ; => 1.0
```

**See also:** `float`, `int?`, `number?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L252)

## `double-array`

```phel
(double-array size-or-seq)
```

Creates a PHP array of doubles (same as float-array in PHP).
   Given a size, fills with `0.0`. Given a sequence, coerces each element to float.

**Example**

```phel
(double-array 3) ; => PHP array [0.0, 0.0, 0.0]
(double-array [1 2]) ; => PHP array [1.0, 2.0]
```

**See also:** `float-array`, `int-array`, `long-array`, `short-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L144)

## `double?`

```phel
(double? x)
```

Returns true if `x` is a floating-point number, false otherwise.
   Alias for `float?`, matching Clojure's `double?` naming. Since Phel
   uses PHP floats (IEEE 754 doubles) there is no separate single-precision
   float type.

**See also:** `float?`, `number?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L128)

## `drop`

```phel
(drop n & args)
```

Drops the first `n` elements of `coll`. Returns a lazy sequence.
  When called with n only, returns a transducer.

**Example**

```phel
(drop 2 [1 2 3 4 5]) ; => (3 4 5)
```

**See also:** `take`, `drop-last`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L320)

## `drop-last`

```phel
(drop-last coll)
```

```phel
(drop-last n coll)
```

Drops the last `n` elements of `coll`. `n` defaults to `1` when omitted,
   matching Clojure's `(drop-last coll)` single-arity form. Returns an empty
   sequence when `coll` is `nil`. Works with any seqable collection including
   lazy sequences and ranges.

**Example**

```phel
(drop-last [1 2 3 4 5]) ; => (1 2 3 4)
(drop-last 2 [1 2 3 4 5]) ; => (1 2 3)
(drop-last 5 nil) ; => ()
```

**See also:** `drop`, `butlast`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L343)

## `drop-while`

```phel
(drop-while pred & args)
```

Drops all elements at the front of `coll` where `(pred x)` is true. Returns a lazy sequence.
  When called with pred only, returns a transducer.

**Example**

```phel
(drop-while #(< % 5) [1 2 3 4 5 6 3 2 1]) ; => (5 6 3 2 1)
```

**See also:** `take-while`, `drop`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L370)

## `empty`

```phel
(empty coll)
```

Returns an empty collection of the same category as `coll`, or nil.

**Example**

```phel
(empty [1 2 3]) ; => []
(empty {:a 1}) ; => {}
```

**See also:** `empty?`, `not-empty`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L325)

## `empty?`

```phel
(empty? x)
```

Returns true if x would be 0, "" or empty collection, false otherwise.
  Safe on infinite/lazy sequences: checks the first element instead of counting.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L308)

## `eval`

```phel
(eval form)
```

Evaluates a form and return the evaluated results.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L722)

## `even?`

```phel
(even? x)
```

Checks if `x` is even.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L176)

## `every?`

```phel
(every? pred coll)
```

Returns true if predicate is true for every element in collection, false otherwise.
  Alias for `all?`.

**Example**

```phel
(every? even? [2 4 6 8]) ; => true
```

**See also:** `all?`, `not-every?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L198)

## `ex-cause`

```phel
(ex-cause ex)
```

Returns the cause of an exception, or nil.

**See also:** `ex-info`, `ex-data`, `ex-message`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/exceptions.phel#L34)

## `ex-data`

```phel
(ex-data ex)
```

Returns the data map from an ex-info exception, or nil if not an ExInfoException.

**See also:** `ex-info`, `ex-message`, `ex-cause`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/exceptions.phel#L21)

## `ex-info`

```phel
(ex-info msg data)
```

```phel
(ex-info msg data cause)
```

Creates an exception with a message and a data map. Optionally takes a cause.

**Example**

```phel
(throw (ex-info "Invalid input" {:field :email}))
```

**See also:** `ex-data`, `ex-message`, `ex-cause`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/exceptions.phel#L12)

## `ex-message`

```phel
(ex-message ex)
```

Returns the message of an exception.

**See also:** `ex-info`, `ex-data`, `ex-cause`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/exceptions.phel#L28)

## `extend-protocol`

```phel
(extend-protocol protocol-name & specs)
```

Convenience macro that extends a single protocol to multiple types.
  Alternates type-specs and method implementations.

  Equivalent to multiple `extend-type` calls.

**Example**

```phel
(extend-protocol Describable
  :string (describe [s] s)
  :int (describe [n] (str n)))
```

**See also:** `extend-type`, `defprotocol`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L342)

## `extend-type`

```phel
(extend-type type-spec & specs)
```

Extends a type with protocol method implementations.

  type-spec can be:
  - `nil` for the nil type
  - a type keyword matching what `type` returns: `:string`, `:int`, `:float`,
    `:boolean`, `:keyword`, `:symbol`, `:vector`, `:list`, `:hash-map`, `:set`,
    `:var`, `:function`, `:php/array`
  - a symbol for struct names (resolved in current namespace)
  - a string for explicit PHP class names (cross-namespace structs)

  Note: `:struct` and `:php/object` cannot be used as type-specs because
  protocol dispatch resolves these to their specific PHP class names.
  Use a struct symbol or PHP class name string instead.

**Example**

```phel
(extend-type :string Stringable (to-string [s] s))
```

**See also:** `defprotocol`, `satisfies?`, `extends?`, `protocol-type-key`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L252)

## `extends?`

```phel
(extends? protocol type-key)
```

Returns true if the given type-key has implementations for all methods
  of the protocol. type-key should match what protocol-type-key returns.

**Example**

```phel
(extends? Stringable :string)
```

**See also:** `satisfies?`, `defprotocol`, `extend-type`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L331)

## `extreme`

```phel
(extreme order args)
```

Returns the most extreme value in `args` based on the binary `order` function.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L351)

## `false?`

```phel
(false? x)
```

Checks if value is exactly false (not just falsy).

**Example**

```phel
(false? nil) ; => false
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L257)

## `ffirst`

```phel
(ffirst coll)
```

Same as `(first (first coll))`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L41)

## `file-seq`

```phel
(file-seq path)
```

Returns a lazy sequence of all files and directories in a directory tree.

**Example**

```phel
(filter #(php/str_ends_with % ".phel") (file-seq "src/")) ; => ["src/file1.phel" "src/file2.phel" ...]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L137)

## `filter`

```phel
(filter pred & args)
```

Returns a lazy sequence of elements where predicate returns true.
  When called with pred only, returns a transducer.

**Example**

```phel
(filter even? [1 2 3 4 5 6]) ; => (2 4 6)
```

**See also:** `remove`, `keep`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L458)

## `finally`

```phel
(finally expr*)
```

Evaluate expressions after the try body and all matching catches have completed. The finally block runs regardless of whether an exception was thrown.

**Example**

```phel
(defn risky-operation [] (throw (php/new \Exception "Error!")))
(defn cleanup [] (println "Cleanup!"))
(try (risky-operation) (catch \Exception e nil) (finally (cleanup)))
```

## `find`

```phel
(find pred coll)
```

Returns the first item in `coll` where `(pred item)` evaluates to true.

**Example**

```phel
(find #(> % 5) [1 2 3 6 7 8]) ; => 6
```

**See also:** `find-index`, `filter`, `some?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L523)

## `find-hierarchy-method`

```phel
(find-hierarchy-method methods dispatch-val)
```

Finds the best matching method for dispatch-val using the global hierarchy.
  Returns the method function or nil. Used internally by defmulti.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L159)

## `find-index`

```phel
(find-index pred coll)
```

Returns the index of the first item in `coll` where `(pred item)` evaluates to true.

**Example**

```phel
(find-index #(> % 5) [1 2 3 6 7 8]) ; => 3
```

**See also:** `find`, `filter`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L533)

## `first`

Returns the first element of a sequence, or nil if empty.

  Maps are treated as a sequence of entries: `(first {:a 1})` returns the
  first `[:a 1]` vector. Strings are treated as sequences of multibyte
  characters.

**Example**

```phel
(first [1 2 3]) ; => 1
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L95)

## `flatten`

```phel
(flatten coll)
```

Flattens nested sequential structure into a lazy sequence of all leaf values.

**Example**

```phel
(flatten [[1 2] [3 [4 5]] 6]) ; => (1 2 3 4 5 6)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L233)

## `float`

```phel
(float x)
```

Coerces `x` to a float. In PHP there is no distinction between float and
   double; both map to the same native PHP float type. Delegates to PHP's
   `floatval`, so non-numeric strings return `0.0` and `nil` returns `0.0`.

**Example**

```phel
(float 1) ; => 1.0
```

**See also:** `double`, `int?`, `number?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L243)

## `float-array`

```phel
(float-array size-or-seq)
```

Creates a PHP array of floats. Given a size, fills with `0.0`.
   Given a sequence, coerces each element to float via `floatval`.

**Example**

```phel
(float-array 3) ; => PHP array [0.0, 0.0, 0.0]
(float-array [1 2]) ; => PHP array [1.0, 2.0]
```

**See also:** `double-array`, `int-array`, `long-array`, `short-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L136)

## `float?`

```phel
(float? x)
```

Returns true if `x` is float point number, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L70)

## `fn`

```phel
(fn [params*] expr*)
```

Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.

**Example**

```phel
(fn [x y] (+ x y))
```

## `fn?`

```phel
(fn? x)
```

Returns true if `x` is a function, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L206)

## `fnext`

```phel
(fnext coll)
```

Same as `(first (next coll))`.

**Example**

```phel
(fnext [1 2 3]) ; => 2
```

**See also:** `second`, `ffirst`, `nfirst`, `nnext`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L69)

## `fnil`

```phel
(fnil f & defaults)
```

Returns a function that replaces nil arguments with the provided defaults
  before calling f. The number of defaults determines how many leading arguments
  are nil-checked.

**Example**

```phel
(let [safe-inc (fnil inc 0)] (safe-inc nil)) ; => 1
```

**See also:** `partial`, `comp`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L130)

## `for`

```phel
(for head & body)
```

List comprehension. The head of the loop is a vector that contains a
  sequence of bindings modifiers and options. A binding is a sequence of three
  values `binding :verb expr`. Where `binding` is a binding as
  in let and `:verb` is one of the following keywords:

  * `:range` loop over a range by using the range function.
  * `:in` loops over all values of a collection (including strings).
  * `:keys` loops over all keys/indexes of a collection.
  * `:pairs` loops over all key-value pairs of a collection.

  After each loop binding, additional modifiers can be applied. Modifiers
  have the form `:modifier argument`. The following modifiers are supported:

  * `:while` breaks the loop if the expression is falsy.
  * `:let` defines additional bindings.
  * `:when` only evaluates the loop body if the condition is true.

  Finally, additional options can be set:

  * `:reduce [accumulator initial-value]` Instead of returning a list,
     it reduces the values into `accumulator`. Initially `accumulator`
     is bound to `initial-value`.

**Example**

```phel
(for [x :in [1 2 3]] (* x 2)) ; => [2 4 6]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L221)

## `force`

```phel
(force x)
```

If x is a Delay, forces it and returns its cached value. Otherwise returns x.

**Example**

```phel
(force (delay 42)) ; => 42
```

**See also:** `delay`, `deref`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/lazy.phel#L20)

## `foreach`

```phel
(foreach [value valueExpr] expr*)
```

```phel
(foreach [key value valueExpr] expr*)
```

The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.

**Example**

```phel
(foreach [x [1 2 3]] (println x))
```

## `format`

```phel
(format fmt & xs)
```

Returns a formatted string. See PHP's [sprintf](https://www.php.net/manual/en/function.sprintf.php) for more information.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L57)

## `frequencies`

```phel
(frequencies coll)
```

Returns a map from distinct items in `coll` to the number of times they appear.

  Works with vectors, lists, sets, and strings.

**Example**

```phel
(frequencies [:a :b :a :c :b :a]) ; => {:a 3 :b 2 :c 1}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L600)

## `full-name`

```phel
(full-name x)
```

Return the namespace and name string of a string, keyword or symbol.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L708)

## `function?`

```phel
(function? x)
```

Returns true if `x` is a function, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L220)

## `gensym`

```phel
(gensym)
```

Generates a new unique symbol.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/strings.phel#L25)

## `get`

```phel
(get ds k & [opt])
```

Gets the value at key in a collection. Returns default if not found.

**Example**

```phel
(get {:a 1} :a) ; => 1
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L57)

## `get-in`

```phel
(get-in ds ks & [opt])
```

Accesses a value in a nested data structure via a sequence of keys.

  Returns `opt` (default nil) if the path doesn't exist.

**Example**

```phel
(get-in {:a {:b {:c 42}}} [:a :b :c]) ; => 42
```

**See also:** `assoc-in`, `update-in`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L256)

## `get-validator`

```phel
(get-validator variable)
```

Returns the validator function of a variable, or nil.

**See also:** `set-validator!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L139)

## `group-by`

```phel
(group-by f coll)
```

Returns a map of the elements of coll keyed by the result of `f` on each element.

**Example**

```phel
(group-by count ["a" "bb" "c" "ddd" "ee"]) ; => {1 ["a" "c"] 2 ["bb" "ee"] 3 ["ddd"]}
```

**See also:** `partition-by`, `frequencies`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L969)

## `hash-map`

```phel
(hash-map & xs)
```

Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.

**Example**

```phel
(hash-map :name "Alice" :age 30) ; => {:name "Alice" :age 30}
```

## `hash-map?`

```phel
(hash-map? x)
```

Returns true if `x` is a hash map, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L238)

## `hash-set`

```phel
(hash-set & xs)
```

Creates a new Set from the given arguments. Shortcut: #{}

**Example**

```phel
(hash-set 1 2 3) ; => #{1 2 3}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/collections.phel#L15)

## `id`

```phel
(id a & more)
```

Checks if all values are identical. Same as `a === b` in PHP.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L93)

## `ident?`

```phel
(ident? x)
```

Returns true if `x` is a symbol or keyword.

**Example**

```phel
(ident? 'x) ; => true
(ident? :a) ; => true
(ident? 42) ; => false
```

**See also:** `symbol?`, `keyword?`, `simple-ident?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L165)

## `identical?`

```phel
(identical? a & more)
```

Checks if all values are identical. Same as `a === b` in PHP.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L83)

## `identity`

```phel
(identity x)
```

Returns its argument.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L326)

## `if`

```phel
(if test then else?)
```

A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

**Example**

```phel
(if (> x 0) "positive" "non-positive")
```

## `if-let`

```phel
(if-let bindings then & [else])
```

If test is true, evaluates then with binding-form bound to the value of test,
  if not, yields else

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L554)

## `if-not`

```phel
(if-not test then & [else])
```

Evaluates then if test is false, else otherwise.

**Example**

```phel
(if-not (< 5 3) "not less" "less") ; => "not less"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L13)

## `if-some`

```phel
(if-some bindings then & [else])
```

Binds name to the value of test. If test is not nil, evaluates then with binding-form
  bound to the value of test, if not, yields else. Unlike if-let, false and 0 are not
  treated as falsy — only nil triggers the else branch.

**See also:** `if-let`, `when-some`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L594)

## `ifn?`

```phel
(ifn? x)
```

Returns true if `x` can be invoked as a function.
   This includes functions, keywords, maps, vectors, sets, and lists.

**Example**

```phel
(ifn? inc) ; => true
(ifn? :a) ; => true
(ifn? {}) ; => true
(ifn? 42) ; => false
```

**See also:** `fn?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L211)

## `inc`

```phel
(inc x)
```

Increments `x` by one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L162)

## `indexed?`

```phel
(indexed? x)
```

Returns true if `x` is an indexed sequence, false otherwise.

  Indexed sequences include lists, vectors, and indexed PHP arrays.

**Example**

```phel
(indexed? [1 2 3]) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L372)

## `inf?`

```phel
(inf? x)
```

Checks if `x` is infinite.

**Example**

```phel
(inf? php/INF) ; => true
```

**See also:** `nan?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L217)

## `instance?`

```phel
(instance? c x)
```

Returns true if `x` is an instance of class `c`, false otherwise.
  Mirrors Clojure's `clojure.core/instance?` argument order (class first,
  value second). `c` should be a literal class reference such as
  `\DateTime` or a `:use`d short name; for runtime class names use
  `(php/is_a x class-name)`.

**Example**

```phel
(instance? \DateTime (php/new \DateTime)) ; => true
```

**See also:** `type`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L276)

## `int`

```phel
(int x)
```

Coerces `x` to an integer. Delegates to PHP's `intval`, so floats are
   truncated toward zero, numeric strings are parsed, and `nil` returns `0`.

**Example**

```phel
(int 1.9) ; => 1
(int "42") ; => 42
```

**See also:** `float`, `double`, `int?`, `number?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L235)

## `int-array`

```phel
(int-array size-or-seq)
```

Creates a PHP array of integers. Given a size, fills with `0`.
   Given a sequence, coerces each element to int via `intval`.
   PHP has no typed arrays, so the result is a plain PHP array.

**Example**

```phel
(int-array 3) ; => PHP array [0, 0, 0]
(int-array [1.5 2.7]) ; => PHP array [1, 2]
```

**See also:** `long-array`, `float-array`, `double-array`, `short-array`, `object-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L119)

## `int?`

```phel
(int? x)
```

Returns true if `x` is an integer number, false otherwise.
   Alias for `integer?`.

   Note that, unlike Clojure, Phel uses PHP integers and there is no
   distinction between standard and fixed-precision integers.
   Integer sizes are also limited by platform (see php/PHP_INT_MAX constant).

**See also:** `integer?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L81)

## `integer?`

```phel
(integer? x)
```

Returns true if `x` is an integer number, false otherwise.

**See also:** `int?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L75)

## `interleave`

```phel
(interleave & colls)
```

Interleaves multiple collections. Returns a lazy sequence.

  Returns elements by taking one from each collection in turn.
  Pads with nil when collections have different lengths.
  Works with infinite sequences.

**Example**

```phel
(interleave [1 2 3] [:a :b :c]) ; => (1 :a 2 :b 3 :c)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L919)

## `interpose`

```phel
(interpose sep & args)
```

Returns elements separated by a separator. Returns a lazy sequence.
  When called with sep only, returns a transducer.

**Example**

```phel
(interpose 0 [1 2 3]) ; => (1 0 2 0 3)
```

**See also:** `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L872)

## `intersection`

```phel
(intersection set & sets)
```

Intersect multiple sets into a new one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L35)

## `into`

```phel
(into to & rest)
```

Returns `to` with all elements of `from` added.

  When `from` is associative, it is treated as a sequence of key-value pairs.
  Supports persistent and transient collections.

**Example**

```phel
(into [] '(1 2 3)) ; => [1 2 3]
```

**See also:** `conj`, `concat`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L86)

## `invert`

```phel
(invert map)
```

Returns a new map where the keys and values are swapped.

  If map has duplicated values, some keys will be ignored.

**Example**

```phel
(invert {:a 1 :b 2 :c 3}) ; => {1 :a 2 :b 3 :c}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1046)

## `isa?`

```phel
(isa? child parent)
```

Returns true if child equals parent, or child is a descendant of parent
  in the global hierarchy.

**Example**

```phel
(do (derive :square :shape) (isa? :square :shape)) ; => true
```

**See also:** `derive`, `parents`, `ancestors`, `descendants`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L88)

## `iterate`

```phel
(iterate f x)
```

Returns an infinite lazy sequence of x, (f x), (f (f x)), and so on.

**Example**

```phel
(take 5 (iterate inc 0)) ; => (0 1 2 3 4)
```

**See also:** `repeatedly`, `cycle`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L812)

## `iteration`

```phel
(iteration step opts)
```

Creates a lazy sequence from successive calls to `step`.
  `step` is called with a key (starting with `:initk`) and returns a result.
  `:kf` extracts the next key, `:vf` extracts the value from the result.
  Terminates when the result is nil.

  Options map keys:
    :kf     — key function (default: identity)
    :vf     — value function (default: identity)
    :initk  — initial key (default: nil)

**Example**

```phel
(iteration fetch-page {:kf :next-token :vf :items :initk nil})
```

**See also:** `iterate`, `repeatedly`, `lazy-seq`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/lazy.phel#L37)

## `juxt`

```phel
(juxt & fs)
```

Takes a list of functions and returns a new function that is the juxtaposition of those functions.

**Example**

```phel
((juxt inc dec #(* % 2)) 10) => [11 9 20]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L113)

## `keep`

```phel
(keep pred & args)
```

Returns a lazy sequence of non-nil results of applying function to elements.
  When called with f only, returns a transducer.

**Example**

```phel
(keep #(when (even? %) (* % %)) [1 2 3 4 5]) ; => (4 16)
```

**See also:** `keep-indexed`, `filter`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L485)

## `keep-indexed`

```phel
(keep-indexed pred & args)
```

Returns a lazy sequence of non-nil results of `(pred i x)`.
  When called with f only, returns a transducer.

**Example**

```phel
(keep-indexed #(when (even? %1) %2) ["a" "b" "c" "d"]) ; => ("a" "c")
```

**See also:** `keep`, `map-indexed`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L503)

## `key`

```phel
(key entry)
```

Returns the key of a map entry (a two-element vector `[key value]`).

**Example**

```phel
(key (first (pairs {:a 1}))) ; => :a
```

**See also:** `val`, `keys`, `pairs`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L629)

## `keys`

```phel
(keys coll)
```

Returns a sequence of all keys in a map, or `nil` when the map is `nil`
  or empty.

**Example**

```phel
(keys {:a 1 :b 2}) ; => (:a :b)
(keys nil) ; => nil
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L613)

## `keyword`

```phel
(keyword x)
```

```phel
(keyword ns nm)
```

Creates a new Keyword.

  Arity-1 accepts a string, keyword, or symbol. Returns `nil` when `x` is `nil`.
  If `x` is already a keyword, it is returned unchanged.

  Arity-2 builds a namespaced keyword from the namespace and name parts; returns
  `nil` when `name` is `nil`.

**Example**

```phel
(keyword "name") ; => :name
(keyword :abc) ; => :abc
(keyword "ns" "name") ; => :ns/name
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L56)

## `keyword?`

```phel
(keyword? x)
```

Returns true if `x` is a keyword, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L155)

## `kvs`

```phel
(kvs coll)
```

Returns a vector of key-value pairs like `[k1 v1 k2 v2 k3 v3 ...]`.

**Example**

```phel
(kvs {:a 1 :b 2}) ; => [:a 1 :b 2]
```

**See also:** `pairs`, `keys`, `values`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L659)

## `last`

```phel
(last coll)
```

Returns the last element of `coll` or nil if `coll` is empty or nil.

**Example**

```phel
(last [1 2 3]) ; => 3
```

**See also:** `first`, `peek`, `butlast`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L354)

## `lazy-cat`

```phel
(lazy-cat & colls)
```

Concatenates collections into a lazy sequence (expands to concat).

**Example**

```phel
(lazy-cat [1 2] [3 4]) ; => (1 2 3 4)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L806)

## `lazy-seq`

```phel
(lazy-seq & body)
```

Creates a lazy sequence that evaluates the body only when accessed.

**Example**

```phel
(lazy-seq (cons 1 (lazy-seq nil))) ; => (1)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L797)

## `let`

```phel
(let [bindings*] expr*)
```

Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.

**Example**

```phel
(let [x 1 y 2] (+ x y)) ; => 3
```

## `letfn`

```phel
(letfn bindings & body)
```

Defines mutually recursive local functions.

  bindings is a vector of function specs: (letfn [(f [params] body) (g [params] body)] expr)
  All function names are in scope within all function bodies and the body expression,
  enabling mutual recursion.

**Example**

```phel
(letfn [(my-even? [n] (if (zero? n) true (my-odd? (dec n))))
        (my-odd? [n] (if (zero? n) false (my-even? (dec n))))]
  (my-even? 10))
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L646)

## `line-seq`

```phel
(line-seq filename)
```

Returns a lazy sequence of lines from a file.

**Example**

```phel
(take 10 (line-seq "large-file.txt")) ; => ["line1" "line2" ...]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L125)

## `list`

```phel
(list & xs)
```

Creates a new list. If no argument is provided, an empty list is created.

**Example**

```phel
(list 1 2 3) ; => '(1 2 3)
```

## `list?`

```phel
(list? x)
```

Returns true if `x` is a list, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L250)

## `long`

```phel
(long x)
```

Coerces `x` to a long integer. In PHP there is no distinction between int
   and long; both map to the same native PHP int type. Alias for `int`.

**Example**

```phel
(long 1.9) ; => 1
```

**See also:** `int`, `float`, `double`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L260)

## `long-array`

```phel
(long-array size-or-seq)
```

Creates a PHP array of longs (same as int-array in PHP).
   Given a size, fills with `0`. Given a sequence, coerces each element to int.

**Example**

```phel
(long-array 3) ; => PHP array [0, 0, 0]
(long-array [1.5 2.7]) ; => PHP array [1, 2]
```

**See also:** `int-array`, `float-array`, `double-array`, `short-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L128)

## `loop`

```phel
(loop [bindings*] expr*)
```

Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.

**Example**

```phel
(loop [i 0] (if (< i 5) (do (println i) (recur (inc i)))))
```

## `macroexpand`

```phel
(macroexpand form)
```

Recursively expands the given form until it is no longer a macro call.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/macroexpand.phel#L32)

## `macroexpand-1`

```phel
(macroexpand-1 form)
```

Expands the given form once if it is a macro call.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/macroexpand.phel#L12)

## `make-hierarchy`

```phel
(make-hierarchy)
```

Creates a fresh, empty hierarchy.

  Returns a map with `:parents`, `:descendants`, and `:ancestors` keys, each
  holding an empty map. Matches Clojure's hierarchy shape so consumers can
  destructure any of the three relationship views.

**See also:** `derive`, `isa?`, `parents`, `ancestors`, `descendants`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L51)

## `map`

```phel
(map f & colls)
```

Returns a lazy sequence of the result of applying `f` to all of the first items in each coll,
   followed by applying `f` to all the second items in each coll until anyone of the colls is exhausted.

  When given a single collection, applies the function to each element.
  With multiple collections, applies the function to corresponding elements from each collection,
  stopping when the shortest collection is exhausted.

**Example**

```phel
(map inc [1 2 3]) ; => (2 3 4)
```

**See also:** `filter`, `reduce`, `map-indexed`, `mapcat`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L52)

## `map-indexed`

```phel
(map-indexed f coll)
```

Maps a function over a collection with index. Returns a lazy sequence.

  Applies `f` to each element in `xs`. `f` is a two-argument function where
  the first argument is the index (0-based) and the second is the element itself.
  Works with infinite sequences.

**Example**

```phel
(map-indexed vector [:a :b :c]) ; => ([0 :a] [1 :b] [2 :c])
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L902)

## `map?`

```phel
(map? x)
```

Returns true if `x` is a hash map, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L232)

## `mapcat`

```phel
(mapcat f & args)
```

Maps a function over one or more collections and concatenates the results.
  Returns a lazy sequence. When called with `f` alone, returns a transducer.

  With a single collection behaves like `(apply concat (map f coll))`. With
  multiple collections, `f` is called with corresponding elements from each
  (stopping when the shortest is exhausted) and the resulting sequences are
  concatenated.

**Example**

```phel
(mapcat reverse [[1 2] [3 4]]) ; => (2 1 4 3)
(mapcat list [:a :b :c] [1 2 3]) ; => (:a 1 :b 2 :c 3)
```

**See also:** `map`, `cat`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L849)

## `max`

```phel
(max & numbers)
```

Returns the numeric maximum of all numbers.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L361)

## `max-key`

```phel
(max-key k x & more)
```

Returns the arg for which (k arg) is largest. On ties, returns the latest argument, matching Clojure semantics.

**Example**

```phel
(max-key count "bb" "aaa" "b") ; => "aaa"
```

**See also:** `min-key`, `max`, `sort-by`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L373)

## `mean`

```phel
(mean xs)
```

Returns the mean of `xs`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L392)

## `median`

```phel
(median xs)
```

Returns the median of `xs`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L397)

## `memoize`

```phel
(memoize f)
```

Returns a memoized version of the function `f`. The memoized function
  caches the return value for each set of arguments.

**Example**

```phel
(defn fact [n]
  (if (zero? n)
    1
    (* n (fact (dec n)))))
(def fact-memo (memoize fact))
```

**See also:** `memoize-lru`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L149)

## `memoize-lru`

```phel
(memoize-lru f)
```

```phel
(memoize-lru f max-size)
```

Returns a memoized version of the function `f` with an LRU (Least Recently Used)
  cache limited to `max-size` entries. When the cache exceeds `max-size`, the
  least recently used entry is evicted. This prevents unbounded memory growth
  in long-running processes.

  Without arguments, uses a default cache size of 128 entries.

**Example**

```phel
(defn fact [n]
  (if (zero? n)
    1
    (* n (fact (dec n)))))
(def fact-memo (memoize-lru fact 100))
```

**See also:** `memoize`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L169)

## `merge`

```phel
(merge & maps)
```

Merges multiple maps into one new map.

  If a key appears in more than one collection, later values replace previous ones.

**Example**

```phel
(merge {:a 1 :b 2} {:b 3 :c 4}) ; => {:a 1 :b 3 :c 4}
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1009)

## `merge-with`

```phel
(merge-with f & hash-maps)
```

Merges multiple maps into one new map. If a key appears in more than one
   collection, the result of `(f current-val next-val)` is used.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L249)

## `meta`

Gets the metadata attached to a value.
  For a quoted symbol (`(meta 'foo)`) the definition metadata registered via `def` is returned.
  For any other expression the value is looked up at runtime and its `MetaInterface` metadata returned.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/meta.phel#L23)

## `min`

```phel
(min & numbers)
```

Returns the numeric minimum of all numbers.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L356)

## `min-key`

```phel
(min-key k x & more)
```

Returns the arg for which (k arg) is smallest. On ties, returns the latest argument, matching Clojure semantics.

**Example**

```phel
(min-key count "bb" "aaa" "b") ; => "b"
```

**See also:** `max-key`, `min`, `sort-by`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L366)

## `name`

```phel
(name x)
```

Returns the name string of a string, keyword or symbol.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L698)

## `namespace`

```phel
(namespace x)
```

Return the namespace string of a symbol or keyword. Nil if not present.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L703)

## `nan?`

```phel
(nan? x)
```

Checks if `x` is not a number.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L206)

## `nat-int?`

```phel
(nat-int? x)
```

Returns true if `x` is a non-negative integer (zero or positive).

**Example**

```phel
(nat-int? 0) ; => true
(nat-int? 1) ; => true
(nat-int? -1) ; => false
```

**See also:** `int?`, `pos-int?`, `neg-int?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L106)

## `neg-int?`

```phel
(neg-int? x)
```

Returns true if `x` is a negative integer.

**Example**

```phel
(neg-int? -1) ; => true
(neg-int? 0) ; => false
(neg-int? 1) ; => false
```

**See also:** `int?`, `pos-int?`, `nat-int?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L92)

## `neg?`

```phel
(neg? x)
```

Checks if `x` is smaller than zero.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L201)

## `next`

Returns the sequence after the first element, or nil if empty.

**Example**

```phel
(next [1 2 3]) ; => [2 3]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core.phel#L58)

## `nfirst`

```phel
(nfirst coll)
```

Same as `(next (first coll))`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L64)

## `nil?`

```phel
(nil? x)
```

Returns true if value is nil, false otherwise.

**Example**

```phel
(nil? (get {:a 1} :b)) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L263)

## `nnext`

```phel
(nnext coll)
```

Same as `(next (next coll))`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L76)

## `not`

```phel
(not x)
```

Returns true if value is falsy (nil or false), false otherwise.

**Example**

```phel
(not nil) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L111)

## `not-any?`

```phel
(not-any? pred coll)
```

Returns true if `(pred x)` is logical false for every `x` in `coll`
   or if `coll` is empty. Otherwise returns false.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L229)

## `not-empty`

```phel
(not-empty coll)
```

Returns `coll` if it contains elements, otherwise nil.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L318)

## `not-every?`

```phel
(not-every? pred coll)
```

Returns false if `(pred x)` is logical true for every `x` in collection `coll`
   or if `coll` is empty. Otherwise returns true.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L209)

## `not=`

```phel
(not= a & more)
```

Checks if all values are unequal. Same as `a != b` in PHP.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L117)

## `ns`

```phel
(ns name imports*)
```

Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword :use is used to import PHP classes, the keyword :require is used to import Phel modules and the keyword :require-file is used to load php files.

**Example**

```phel
(ns my-app\core (:require phel\string :as str))
```

## `nth`

```phel
(nth coll index)
```

```phel
(nth coll index not-found)
```

Returns the value at `index` in `coll`. Throws an
   OutOfBoundsException if the index is out of range and no
   `not-found` value is supplied. For indexed collections (vectors,
   strings) this is O(1); for sequences it is O(n).

**Example**

```phel
(nth [1 2 3] 1) ; => 2
(nth [1 2 3] 5 :default) ; => :default
```

**See also:** `get`, `first`, `second`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L87)

## `nthnext`

```phel
(nthnext coll n)
```

Returns the nth next of `coll`, `(seq coll)` when `n` is 0.
   Returns nil if there are fewer than `n` elements remaining.

**Example**

```phel
(nthnext [1 2 3 4 5] 2) ; => (3 4 5)
(nthnext [1 2] 5) ; => nil
```

**See also:** `nth`, `nthrest`, `drop`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L150)

## `nthrest`

```phel
(nthrest coll n)
```

Returns the nth rest of `coll`, `coll` when `n` is 0.

**Example**

```phel
(nthrest [1 2 3 4 5] 2) ; => (3 4 5)
```

**See also:** `nth`, `nthnext`, `drop`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L139)

## `number?`

```phel
(number? x)
```

Returns true if `x` is a number, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L113)

## `object-array`

```phel
(object-array size-or-seq)
```

Creates a PHP array of the given size initialized to `nil`, or a PHP
  array containing the elements of the given sequence. Matches Clojure's
  `object-array` for `.cljc` interop — in Phel the result is a plain PHP
  array (accessible via `php/aget`/`php/aset`) since PHP has no typed
  array distinction.

**Example**

```phel
(object-array 3) ; => a PHP array [nil, nil, nil]
(object-array [1 2 3]) ; => a PHP array [1, 2, 3]
```

**See also:** `php-indexed-array`, `to-php-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L42)

## `odd?`

```phel
(odd? x)
```

Checks if `x` is odd.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L181)

## `one?`

```phel
(one? x)
```

Checks if `x` is one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L191)

## `or`

```phel
(or & args)
```

Evaluates expressions left to right, returning the first truthy value or the last value.

**Example**

```phel
(or false nil 42 100) ; => 42
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L34)

## `pairs`

```phel
(pairs coll)
```

Gets the pairs of an associative data structure.

**Example**

```phel
(pairs {:a 1 :b 2}) ; => ([:a 1] [:b 2])
```

**See also:** `keys`, `values`, `kvs`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L651)

## `parents`

```phel
(parents tag)
```

Returns the set of immediate parents of tag in the global hierarchy, or nil.

**See also:** `ancestors`, `descendants`, `derive`, `isa?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L130)

## `parse-boolean`

```phel
(parse-boolean s)
```

Parses a string as a boolean. Returns true for "true", false for "false", nil otherwise.

**Example**

```phel
(parse-boolean "true") ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/parsing.phel#L36)

## `parse-double`

```phel
(parse-double s)
```

Parses a string as a float. Returns `nil` for non-numeric input or for
  inputs that are not strings. Accepts `Infinity`, `-Infinity`, and `NaN`
  alongside regular decimal and scientific notation.

**Example**

```phel
(parse-double "3.14") ; => 3.14
(parse-double "Infinity") ; => INF
```

**See also:** `parse-long`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/parsing.phel#L20)

## `parse-long`

```phel
(parse-long s)
```

Parses a string as an integer. Returns nil if parsing fails.

**Example**

```phel
(parse-long "123") ; => 123
```

**See also:** `parse-double`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/parsing.phel#L10)

## `parse-uuid`

```phel
(parse-uuid s)
```

Parses `s` as a canonical UUID string. Returns the lower-cased UUID
  string if valid, or nil otherwise. Since PHP has no UUID type, UUIDs
  are returned as strings.

**Example**

```phel
(parse-uuid "550E8400-E29B-41D4-A716-446655440000")
  ; => "550e8400-e29b-41d4-a716-446655440000"
```

**See also:** `uuid?`, `random-uuid`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L43)

## `partial`

```phel
(partial f & args)
```

Takes a function `f` and fewer than normal arguments of `f` and returns a function
  that a variable number of additional arguments. When call `f` will be called
  with `args` and the additional arguments.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L123)

## `partition`

```phel
(partition n coll)
```

Partitions collection into chunks of size n, dropping incomplete final partition.

**Example**

```phel
(partition 3 [1 2 3 4 5 6 7]) ; => ([1 2 3] [4 5 6])
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1121)

## `partition-all`

```phel
(partition-all n coll)
```

Partitions collection into chunks of size n, including incomplete final partition.

**Example**

```phel
(partition-all 3 [1 2 3 4 5 6 7]) ; => ([1 2 3] [4 5 6] [7])
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1133)

## `partition-by`

```phel
(partition-by f coll)
```

Returns a lazy sequence of partitions. Applies `f` to each value in `coll`, splitting them each time the return value changes.

**Example**

```phel
(partition-by #(< % 3) [1 2 3 4 5 1 2]) ; => [[1 2] [3 4 5] [1 2]]
```

**See also:** `group-by`, `partition`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1071)

## `peek`

```phel
(peek coll)
```

Returns the last element of a sequence, or nil if empty or nil.
  Works on vectors, PHP arrays, lists, and lazy sequences.

**Example**

```phel
(peek [1 2 3]) ; => 3
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L23)

## `persistent`

```phel
(persistent coll)
```

Converts a transient collection back to a persistent collection.

**Example**

```phel
(def t (transient {}))
```

**See also:** `transient`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L27)

## `persistent!`

```phel
(persistent! coll)
```

Converts a transient collection back to a persistent collection.
   Alias for `persistent`, matching Clojure's `persistent!` naming.

**Example**

```phel
(persistent! (conj! (transient []) 1 2 3)) ; => [1 2 3]
```

**See also:** `persistent`, `transient`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L34)

## `phel->php`

```phel
(phel->php x)
```

Recursively converts a Phel data structure to a PHP array.

**Example**

```phel
(phel->php {:a [1 2 3] :b {:c 4}}) ; => (PHP array ["a" => [1, 2, 3], "b" => ["c" => 4]])
```

**See also:** `php->phel`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L681)

## `php->phel`

```phel
(php->phel x)
```

Recursively converts a PHP array to Phel data structures.

  Indexed PHP arrays become vectors, associative PHP arrays become maps.

**Example**

```phel
(php->phel (php-associative-array "a" 1 "b" 2)) ; => {"a" 1 "b" 2}
```

**See also:** `phel->php`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L713)

## `php-array-to-map`

```phel
(php-array-to-map arr)
```

Converts a PHP Array to a Phel map.

**Example**

```phel
(php-array-to-map (php-associative-array "a" 1 "b" 2)) ; => {"a" 1 "b" 2}
```

**See also:** `to-php-array`, `php->phel`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L670)

## `php-array?`

```phel
(php-array? x)
```

Returns true if `x` is a PHP Array, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L287)

## `php-associative-array`

```phel
(php-associative-array & xs)
```

Creates a PHP associative array from key-value pairs.

  Arguments:
    Key-value pairs (must be even number of arguments)

**Example**

```phel
(php-associative-array "name" "Alice" "age" 30) ; => (PHP array ["name" => "Alice", "age" => 30])
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L24)

## `php-indexed-array`

```phel
(php-indexed-array & xs)
```

Creates a PHP indexed array from the given values.

**Example**

```phel
(php-indexed-array 1 2 3) ; => (PHP array [1, 2, 3])
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L18)

## `php-object?`

```phel
(php-object? x)
```

Returns true if `x` is a PHP object, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L297)

## `php-resource?`

```phel
(php-resource? x)
```

Returns true if `x` is a PHP resource, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L292)

## `pop`

```phel
(pop coll)
```

Removes the last element of the array `coll`. If the array is empty returns nil.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L49)

## `pop!`

```phel
(pop! tcoll)
```

Removes the last element from a transient vector, mutating it in place.
   Raises `InvalidArgumentException` when `tcoll` is not a transient vector.
   Matches Clojure's `pop!` semantics.

**Example**

```phel
(persistent! (pop! (transient [1 2 3]))) ; => [1 2]
```

**See also:** `pop`, `conj!`, `transient`, `persistent!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L171)

## `pos-int?`

```phel
(pos-int? x)
```

Returns true if `x` is a positive integer (greater than zero).

**Example**

```phel
(pos-int? 1) ; => true
(pos-int? 0) ; => false
(pos-int? -1) ; => false
```

**See also:** `int?`, `neg-int?`, `nat-int?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L99)

## `pos?`

```phel
(pos? x)
```

Checks if `x` is greater than zero.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L196)

## `print`

```phel
(print & xs)
```

Prints the given values to the default output stream. Returns nil.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L44)

## `print-str`

```phel
(print-str & xs)
```

Same as print. But instead of writing it to an output stream, the resulting string is returned.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L30)

## `printf`

```phel
(printf fmt & xs)
```

Output a formatted string. See PHP's [printf](https://www.php.net/manual/en/function.printf.php) for more information.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L62)

## `println`

```phel
(println & xs)
```

Same as print followed by a newline.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L50)

## `protocol-type-key`

```phel
(protocol-type-key x)
```

Returns the dispatch key for protocol dispatch. Returns a type keyword
  for primitive types, or the PHP class name string for objects/structs.

  Optimized to avoid the full `type` cond chain: checks scalars first
  (most common in tight loops), then objects.

**See also:** `defprotocol`, `extend-type`, `satisfies?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L191)

## `push`

```phel
(push coll x)
```

Inserts `x` at the end of the sequence `coll`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L42)

## `put`

```phel
(put ds key value)
```

Puts `value` mapped to `key` on the datastructure `ds`. Returns `ds`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L203)

## `put-in`

```phel
(put-in ds ks v)
```

Puts a value into a nested data structure.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L277)

## `quote`

```phel
(quote form)
```

Returns the unevaluated form.

**Example**

```phel
(quote (+ 1 2)) ; => '(+ 1 2)
```

## `rand`

```phel
(rand)
```

Returns a random number between 0 and 1.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L336)

## `rand-int`

```phel
(rand-int n)
```

Returns a random number between 0 and `n`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L341)

## `rand-nth`

```phel
(rand-nth xs)
```

Returns a random item from xs.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L346)

## `random-uuid`

```phel
(random-uuid)
```

Returns a random UUID v4 string.

**Example**

```phel
(random-uuid) ; => "550e8400-e29b-41d4-a716-446655440000"
```

**See also:** `uuid?`, `parse-uuid`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L25)

## `range`

```phel
(range & args)
```

Creates a lazy sequence of numbers. With no arguments returns an infinite
  sequence starting at 0. With one argument returns (0..n). With two (start..end).
  With three (start..end step). Note: the infinite sequence is bounded by PHP_INT_MAX.

**Example**

```phel
(range 5) ; => (0 1 2 3 4)
```

**See also:** `iterate`, `repeat`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L158)

## `ratio?`

```phel
(ratio? _)
```

Always returns false. Phel has no Ratio type — Clojure-style ratio
  literals like `1/2` are accepted by the reader but evaluate to floats
  (`num / den`). Provided for `.cljc` interop so cross-host code can
  call `ratio?` without compilation errors.

**Example**

```phel
(ratio? 1/2) ; => false
```

**See also:** `number?`, `float?`, `double?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L118)

## `re-find`

```phel
(re-find re s)
```

Returns the first match of pattern in string, or nil if no match.
  If the pattern has groups, returns a vector of [full-match group1 group2 ...].

**Example**

```phel
(re-find #"\d+" "abc123def") ; => "123"
```

**See also:** `re-seq`, `re-matches`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L351)

## `re-matches`

```phel
(re-matches re s)
```

Returns the match, if any, of string to pattern. If the pattern has groups,
  returns a vector of [full-match group1 group2 ...]. Returns nil if no match.
  Unlike re-find, the entire string must match.

**Example**

```phel
(re-matches #"(\d+)-(\d+)" "12-34") ; => ["12-34" "12" "34"]
```

**See also:** `re-find`, `re-seq`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L365)

## `re-pattern`

```phel
(re-pattern s)
```

Returns a PCRE pattern string from `s`. If `s` is already delimited,
  returns it as-is. Otherwise wraps in `/` delimiters.

**Example**

```phel
(re-pattern "\\d+") ; => "/\\d+/"
```

**See also:** `re-find`, `re-matches`, `re-seq`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L326)

## `re-seq`

```phel
(re-seq re s)
```

Returns a sequence of successive matches of pattern in string.

**Example**

```phel
(re-seq #"\d+" "a1b2c3") ; => ["1" "2" "3"]
```

**See also:** `re-find`, `re-matches`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L339)

## `read-file-lazy`

```phel
(read-file-lazy filename)
```

```phel
(read-file-lazy filename chunk-size)
```

Returns a lazy sequence of byte chunks from a file.

**Example**

```phel
(take 5 (read-file-lazy "large-file.bin" 1024)) ; => ["chunk1" "chunk2" ...]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L149)

## `read-string`

```phel
(read-string s)
```

Reads the first phel expression from the string s.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L713)

## `realized?`

```phel
(realized? coll)
```

Returns true if a lazy sequence, delay, or future has been realized, false otherwise.

**Example**

```phel
(realized? (take 5 (iterate inc 1))) ; => false
```

**See also:** `delay`, `force`, `lazy-seq`, `future`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L958)

## `recur`

```phel
(recur expr*)
```

Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.

**Example**

```phel
(loop [n 5 acc 1] (if (<= n 1) acc (recur (dec n) (* acc n))))
```

## `reduce`

```phel
(reduce f & args)
```

Reduces collection to a single value by repeatedly applying function to accumulator and elements.
  Respects early termination via `(reduced val)`.

**Example**

```phel
(reduce + [1 2 3 4]) ; => 10
```

**See also:** `transduce`, `into`, `reduced`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L50)

## `reduced`

```phel
(reduced x)
```

Wraps `x` in a Reduced, signaling early termination from reduce/transduce.

**See also:** `reduced?`, `unreduced`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L18)

## `reduced?`

```phel
(reduced? x)
```

Returns true if `x` is a Reduced value.

**See also:** `reduced`, `unreduced`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L24)

## `reify`

```phel
(reify & specs)
```

Creates an anonymous object implementing one or more protocols.
  Method bodies close over local bindings. Each instance carries its
  own captured state, so reify works correctly inside loops.

  Syntax:
    (reify
      ProtocolName
      (method-name [this arg1] body)
      AnotherProtocol
      (another-method [this] body))

**Example**

```phel
(reify Speakable (speak [this] "hello"))
```

**See also:** `defprotocol`, `extend-type`, `satisfies?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L372)

## `remove`

```phel
(remove pred & args)
```

Returns a lazy sequence of elements where predicate returns false.
   Opposite of filter. When called with pred only, returns a transducer.

**Example**

```phel
(remove even? [1 2 3 4 5 6]) ; => (1 3 5)
```

**See also:** `filter`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L474)

## `remove-tap`

```phel
(remove-tap f)
```

Removes `f` from the tap set. Returns nil.

**See also:** `add-tap`, `tap>`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/tap.phel#L24)

## `remove-watch`

```phel
(remove-watch variable key)
```

Removes a watch function from a variable by key.

**See also:** `add-watch`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L122)

## `rename-keys`

```phel
(rename-keys m kmap)
```

Returns the map with keys renamed according to kmap.
  Keys not present in kmap are left unchanged.

**Example**

```phel
(rename-keys {:a 1 :b 2 :c 3} {:a :x :b :y}) ; => {:x 1 :y 2 :c 3}
```

**See also:** `select-keys`, `keys`, `vals`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1035)

## `repeat`

```phel
(repeat a & rest)
```

Returns a vector of length n where every element is x.

  With one argument returns an infinite lazy sequence of x.

**Example**

```phel
(repeat 3 :a) ; => [:a :a :a]
```

**See also:** `repeatedly`, `cycle`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L771)

## `repeatedly`

```phel
(repeatedly a & rest)
```

Returns a vector of length n with values produced by repeatedly calling f.

  With one argument returns an infinite lazy sequence of calls to f.

**Example**

```phel
(repeatedly 3 rand) ; => [0.234 0.892 0.456] (random values)
```

**See also:** `repeat`, `iterate`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L783)

## `reset!`

```phel
(reset! variable value)
```

Sets a new value on the given atom. Returns the new value.

**Example**

```phel
(def x (atom 10))
```

**See also:** `atom`, `deref`, `swap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L51)

## `resolve`

```phel
(resolve sym)
```

Resolves the given symbol in the current environment and returns a resolved Symbol with the absolute namespace or nil if it cannot be resolved.

**Example**

```phel
(resolve 'map) ; => phel\core/map
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L740)

## `rest`

```phel
(rest coll)
```

Returns the sequence after the first element, or empty sequence if none.

**Example**

```phel
(rest [1 2 3]) ; => [2 3]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L52)

## `reverse`

```phel
(reverse coll)
```

Reverses the order of the elements in the given sequence.

**Example**

```phel
(reverse [1 2 3 4]) ; => [4 3 2 1]
```

**See also:** `rseq`, `reversible?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L566)

## `reversible?`

```phel
(reversible? coll)
```

Returns true if `coll` can be reverse-iterated in constant time.
  Currently this is true for vectors and sorted-maps.

**Example**

```phel
(reversible? [1 2 3]) ; => true
```

**See also:** `rseq`, `reverse`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L577)

## `rseq`

```phel
(rseq rev)
```

Returns, in constant time, a sequence of the items in `rev` in reverse
  order. `rev` must be reversible (a vector or sorted-map); otherwise an
  exception is thrown. For sorted-maps, returns reversed `[key value]` pairs.
  Returns nil if `rev` is empty.

**Example**

```phel
(rseq [1 2 3]) ; => [3 2 1]
```

**See also:** `reversible?`, `reverse`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L586)

## `run!`

```phel
(run! f coll)
```

Calls `(f x)` for each element in `coll` for side effects. Returns nil.

**Example**

```phel
(run! println [1 2 3])
```

**See also:** `doseq`, `map`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/loops.phel#L9)

## `satisfies?`

```phel
(satisfies? protocol x)
```

Returns true if x's type implements all methods of the given protocol.

**Example**

```phel
(satisfies? Stringable "hello")
```

**See also:** `extends?`, `defprotocol`, `extend-type`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L320)

## `second`

```phel
(second coll)
```

Returns the second element of a sequence, or nil if not present.

**Example**

```phel
(second [1 2 3]) ; => 2
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-basics.phel#L46)

## `select-keys`

```phel
(select-keys m ks)
```

Returns a new map including key value pairs from `m` selected with keys `ks`.

**Example**

```phel
(select-keys {:a 1 :b 2 :c 3} [:a :c]) ; => {:a 1 :c 3}
```

**See also:** `dissoc`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1021)

## `seq`

```phel
(seq coll)
```

Returns a seq on the collection. Strings are converted to a vector of characters.
  Collections are unchanged. Returns nil if coll is empty or nil.

  This function is useful for explicitly converting strings to sequences of characters,
  enabling sequence operations like map, filter, and frequencies.

**Example**

```phel
(seq "hello") ; => ["h" "e" "l" "l" "o"]
```

**See also:** `count`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L339)

## `seq?`

```phel
(seq? x)
```

Returns true if `x` is a seq (implements LazySeqInterface), false otherwise.

**See also:** `seq`, `lazy-seq`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L302)

## `seqable?`

```phel
(seqable? x)
```

Returns true if `(seq x)` is supported: collections (vectors, lists,
   maps, sets, structs), lazy sequences, strings, PHP arrays, and nil.
   Returns false for numbers, booleans, keywords, symbols, and other types.

**Example**

```phel
(seqable? [1 2]) ; => true
(seqable? 42) ; => false
```

**See also:** `seq`, `coll?`, `seq?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L421)

## `sequence`

```phel
(sequence xform coll)
```

Applies transducer `xform` to `coll`, returning a vector of results.

**Example**

```phel
(sequence (comp (filter even?) (map inc)) [1 2 3 4 5]) ; => [3 5]
```

**See also:** `transduce`, `into`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1101)

## `sequential?`

```phel
(sequential? x)
```

Returns true if `x` is a sequential collection (vector, list, or lazy
   sequence), false otherwise. Sequential collections maintain insertion
   order and support indexed or linear access. Maps, sets, and structs
   are not sequential, matching Clojure's `Sequential` marker.

**Example**

```phel
(sequential? [1 2 3]) ; => true
(sequential? {:a 1}) ; => false
```

**See also:** `coll?`, `vector?`, `list?`, `seq?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L394)

## `set`

```phel
(set coll)
```

Coerces a collection to a set. Returns a set containing the distinct elements of `coll`.
  For creating sets from arguments, use `hash-set`.

**Example**

```phel
(set [1 2 3 2 1]) ; => #{1 2 3}
```

**See also:** `hash-set`, `vec`, `into`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L227)

## `set!`

```phel
(set! variable value)
```

Sets a new value to the given variable.

**Example**

```phel
(def x (var 10))
```

**See also:** `var`, `deref`, `swap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L58)

## `set-meta!`

Sets the metadata to a given object.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/meta.phel#L70)

## `set-validator!`

```phel
(set-validator! variable f)
```

Sets a validator function on a variable. The validator is called before any
  state change with the proposed new value. If it returns a falsy value, an
  exception is thrown and the state is not changed. Pass nil to remove.

**Example**

```phel
(set-validator! my-var pos?)
```

**See also:** `get-validator`, `var`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L129)

## `set-var`

```phel
(var value)
```

Variables provide a way to manage mutable state that can be updated with `set!` and `swap!`. Each variable contains a single value. To create a variable use the var function.

**Example**

```phel
(def counter (var 0))
```

## `set?`

```phel
(set? x)
```

Returns true if `x` is a set, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L448)

## `short`

```phel
(short x)
```

Coerces `x` to a signed 16-bit integer in the range `-32768..32767`.
   Decimal values are truncated toward zero (as in Clojure on the JVM).
   Values outside the range or non-numeric inputs raise
   `InvalidArgumentException`. Phel has no dedicated short type, so the
   result is a plain PHP int.

**Example**

```phel
(short 32767) ; => 32767
(short 1.9) ; => 1
(short -32768) ; => -32768
```

**See also:** `int`, `byte`, `long`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L268)

## `short-array`

```phel
(short-array size-or-seq)
```

Creates a PHP array of shorts (16-bit integers). Given a size, fills with `0`.
   Given a sequence, coerces each element to int via `intval`.

**Example**

```phel
(short-array 3) ; => PHP array [0, 0, 0]
(short-array [1.5 2.7]) ; => PHP array [1, 2]
```

**See also:** `int-array`, `long-array`, `float-array`, `double-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L152)

## `shuffle`

```phel
(shuffle coll)
```

Returns a random permutation of coll.

**Example**

```phel
(shuffle [1 2 3 4 5]) ; => [3 1 5 2 4] (random order)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L763)

## `simple-ident?`

```phel
(simple-ident? x)
```

Returns true if `x` is a symbol or keyword without a namespace.

**See also:** `simple-symbol?`, `simple-keyword?`, `ident?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L184)

## `simple-keyword?`

```phel
(simple-keyword? x)
```

Returns true if `x` is a keyword without a namespace.

**See also:** `keyword?`, `simple-ident?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L178)

## `simple-symbol?`

```phel
(simple-symbol? x)
```

Returns true if `x` is a symbol without a namespace.

**See also:** `symbol?`, `simple-ident?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L172)

## `slice`

```phel
(slice coll & [offset & [length]])
```

Extracts a slice of `coll` starting at `offset` with optional `length`.

**Example**

```phel
(slice [1 2 3 4 5] 1 3) ; => [2 3 4]
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L218)

## `slurp`

```phel
(slurp path & [opts])
```

Reads entire file or URL into a string.

**Example**

```phel
(slurp "file.txt") ; => "file contents"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L81)

## `some`

```phel
(some pred coll)
```

Returns the first truthy value of applying predicate to elements, or nil if none found.

**Example**

```phel
(some #(when (> % 10) %) [5 15 8]) ; => 15
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L235)

## `some->`

```phel
(some-> x & forms)
```

Threads `x` through the forms like `->` but stops when a form returns `nil`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L216)

## `some->>`

```phel
(some->> x & forms)
```

Threads `x` through the forms like `->>` but stops when a form returns `nil`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L240)

## `some-fn`

```phel
(some-fn p & ps)
```

Takes a variadic set of predicates and returns a function `f` that,
   when called with any number of arguments, returns the first logical
   true value produced by applying any of the composing predicates to
   any of its arguments, and `nil` otherwise. The returned function
   short-circuits on the first truthy result: arguments after it are
   not inspected, and predicates after it are not tried.
   Predicates are consulted in the order supplied; for a given
   predicate, arguments are consulted left-to-right. Matches Clojure's
   `some-fn` semantics.

**Example**

```phel
((some-fn even? nil?) 1 2) ; => true
((some-fn pos? even?) -3 -1) ; => nil
```

**See also:** `some`, `complement`, `every?`, `not-any?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L96)

## `some?`

```phel
(some? x)
```

```phel
(some? pred coll)
```

With 1 arg, returns true if `x` is not nil (Clojure semantics).
   With 2 args, returns true if `pred` is true for at least one element in `coll`.

**Example**

```phel
(some? 1) ; => true
(some? even? [1 3 5 6 7]) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L218)

## `sort`

```phel
(sort coll & [comp])
```

Returns a sorted vector. If no comparator is supplied compare is used.

**Example**

```phel
(sort [3 1 4 1 5 9 2 6]) ; => [1 1 2 3 4 5 6 9]
```

**See also:** `sort-by`, `compare`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L742)

## `sort-by`

```phel
(sort-by keyfn coll & [comp])
```

Returns a sorted vector where the sort order is determined by comparing `(keyfn item)`.

  If no comparator is supplied compare is used.

**Example**

```phel
(sort-by count ["aaa" "c" "bb"]) ; => ["c" "bb" "aaa"]
```

**See also:** `sort`, `compare`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L751)

## `sorted-map`

```phel
(sorted-map & xs)
```

Creates a new sorted map. Keys are in natural sorted order.
  The number of parameters must be even.

**Example**

```phel
(sorted-map :c 3 :a 1 :b 2) ; keys iterate as :a :b :c
```

**See also:** `sorted-map-by`, `hash-map`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/collections.phel#L21)

## `sorted-map-by`

```phel
(sorted-map-by comp & xs)
```

Creates a new sorted map using the given comparator for key ordering.
  The comparator takes two arguments and returns a negative integer,
  zero, or a positive integer.

**Example**

```phel
(sorted-map-by (fn [a b] (compare b a)) :a 1 :b 2) ; keys iterate as :b :a
```

**See also:** `sorted-map`, `compare`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/collections.phel#L29)

## `sorted-set`

```phel
(sorted-set & xs)
```

Creates a new sorted set. Elements are in natural sorted order.

**Example**

```phel
(sorted-set 3 1 2) ; iterates as 1 2 3
```

**See also:** `sorted-set-by`, `hash-set`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/collections.phel#L38)

## `sorted-set-by`

```phel
(sorted-set-by comp & xs)
```

Creates a new sorted set using the given comparator for element ordering.

**Example**

```phel
(sorted-set-by (fn [a b] (compare b a)) 3 1 2) ; iterates as 3 2 1
```

**See also:** `sorted-set`, `compare`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/collections.phel#L45)

## `sorted?`

```phel
(sorted? coll)
```

Returns true if `coll` is a sorted collection (sorted-map or sorted-set), false otherwise.

**Example**

```phel
(sorted? (sorted-set 1 2 3)) ; => true
```

**See also:** `sorted-map`, `sorted-set`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L453)

## `special-symbol?`

```phel
(special-symbol? s)
```

Returns true if `s` names a special form.

**Example**

```phel
(special-symbol? 'def) ; => true
(special-symbol? 'map) ; => false
```

**See also:** `symbol?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L199)

## `special-symbols`

The set of symbols that name Phel special forms.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L190)

## `spit`

```phel
(spit filename data & [opts])
```

Writes data to file, returning number of bytest that were written or `nil`
  on failure. Accepts `opts` map for overriding default PHP file_put_contents
  arguments, as example to append to file use `{:flags php/FILE_APPEND}`.

  See PHP's [file_put_contents](https://www.php.net/manual/en/function.file-put-contents.php) for more information.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L109)

## `split-at`

```phel
(split-at n coll)
```

Returns a vector of `[(take n coll) (drop n coll)]`.

**Example**

```phel
(split-at 2 [1 2 3 4 5]) ; => [[1 2] [3 4 5]]
```

**See also:** `split-with`, `take`, `drop`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1057)

## `split-with`

```phel
(split-with f coll)
```

Returns a vector of `[(take-while pred coll) (drop-while pred coll)]`.

**Example**

```phel
(split-with #(< % 4) [1 2 3 4 5 6]) ; => [[1 2 3] [4 5 6]]
```

**See also:** `split-at`, `take-while`, `drop-while`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1064)

## `str`

```phel
(str & args)
```

Creates a string by concatenating values together. If no arguments are
provided an empty string is returned. Nil is represented as an empty string.
Booleans are represented as "true" or "false" (matching Clojure semantics).
Otherwise, it tries to call `__toString`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/strings.phel#L55)

## `str-contains?`

```phel
(str-contains? str s)
```

Returns true if str contains s.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L269)

## `string?`

```phel
(string? x)
```

Returns true if `x` is a string, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L137)

## `struct?`

```phel
(struct? x)
```

Returns true if `x` is a struct, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L227)

## `subset?`

```phel
(subset? s1 s2)
```

Returns true if `s1` is a subset of `s2`, i.e. every element in `s1` is also in `s2`.

**Example**

```phel
(subset? (hash-set 1 2) (hash-set 1 2 3)) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L64)

## `sum`

```phel
(sum xs)
```

Returns the sum of all elements is `xs`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L387)

## `superset?`

```phel
(superset? s1 s2)
```

Returns true if `s1` is a superset of `s2`, i.e. every element in `s2` is also in `s1`.

**Example**

```phel
(superset? (hash-set 1 2 3) (hash-set 1 2)) ; => true
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L76)

## `swap!`

```phel
(swap! variable f & args)
```

Atomically swaps the value of the atom to `(apply f current-value args)`.

  Returns the new value after the swap.

**Example**

```phel
(def counter (atom 0))
```

**See also:** `atom`, `reset!`, `deref`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L86)

## `symbol`

```phel
(symbol name-or-ns & [name])
```

Returns a new symbol for given string with optional namespace.

  With one argument, creates a symbol without namespace.
  With two arguments, creates a symbol in the given namespace.

**Example**

```phel
(symbol "foo") ; => foo
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/strings.phel#L14)

## `symbol?`

```phel
(symbol? x)
```

Returns true if `x` is a symbol, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L160)

## `symmetric-difference`

```phel
(symmetric-difference set & sets)
```

Symmetric difference between multiple sets into a new one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L59)

## `take`

```phel
(take n & args)
```

Takes the first `n` elements of `coll`.
  When called with n only, returns a transducer.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L390)

## `take-last`

```phel
(take-last n coll)
```

Takes the last `n` elements of `coll`.

**Example**

```phel
(take-last 3 [1 2 3 4 5]) ; => [3 4 5]
```

**See also:** `take`, `drop-last`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L412)

## `take-nth`

```phel
(take-nth n & args)
```

Returns every nth item in `coll`. Returns a lazy sequence.
  When called with n only, returns a transducer.

**Example**

```phel
(take-nth 2 [0 1 2 3 4 5 6 7 8]) ; => (0 2 4 6 8)
```

**See also:** `take`, `filter`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L436)

## `take-while`

```phel
(take-while pred & args)
```

Takes all elements at the front of `coll` where `(pred x)` is true. Returns a lazy sequence.
  When called with pred only, returns a transducer.

**Example**

```phel
(take-while #(< % 5) [1 2 3 4 5 6 3 2 1]) ; => (1 2 3 4)
```

**See also:** `drop-while`, `take`, `transduce`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L419)

## `tap>`

```phel
(tap> x)
```

Sends `x` to every registered tap. Exceptions thrown by individual taps are
  swallowed so one misbehaving tap does not affect the others. Returns true.

**Example**

```phel
(add-tap println)
(tap> {:event :login :user "alice"})
```

**See also:** `add-tap`, `remove-tap`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/tap.phel#L31)

## `throw`

```phel
(throw exception)
```

Throw an exception.

**Example**

```phel
(throw (php/new \InvalidArgumentException "Invalid input"))
```

## `time`

```phel
(time expr)
```

Evaluates expr and prints the time it took. Returns the value of expr.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L676)

## `to-array`

```phel
(to-array coll)
```

Returns a PHP array containing the elements of `coll`. Accepts any
  collection (vector, list, set, map, PHP array) or `nil`, which yields
  an empty PHP array. Matches Clojure's `to-array` for `.cljc` interop —
  in Phel the result is a plain PHP array since PHP has no `Object[]`.

**Example**

```phel
(to-array [1 2 3]) ; => a PHP array [1, 2, 3]
(to-array nil) ; => a PHP array []
```

**See also:** `to-php-array`, `object-array`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/arrays.phel#L59)

## `to-php-array`

Creates a PHP Array from a sequential data structure.

**See also:** `php-array-to-map`, `phel->php`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/defs.phel#L22)

## `transduce`

```phel
(transduce xform f & args)
```

Reduce with a transformation of `f` (xf). If init is not supplied,
  `(f)` will be called to produce it. `f` should be a reducing function
  that returns the initial value when called with no arguments.

**Example**

```phel
(transduce (map inc) + [1 2 3]) ; => 9
```

**See also:** `reduce`, `into`, `sequence`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L86)

## `transient`

```phel
(transient coll)
```

Converts a persistent collection to a transient collection for efficient updates.

  Transient collections provide faster performance for multiple sequential updates.
  Use `persistent` to convert back.

**Example**

```phel
(def t (transient []))
```

**See also:** `persistent`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transients.phel#L17)

## `tree-seq`

```phel
(tree-seq branch? children root)
```

Returns a vector of the nodes in the tree, via a depth-first walk.
  branch? is a function with one argument that returns true if the given node
  has children.
  children must be a function with one argument that returns the children of the node.
  root the root node of the tree.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L215)

## `true?`

```phel
(true? x)
```

Checks if value is exactly true (not just truthy).

**Example**

```phel
(true? 1) ; => false
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L246)

## `truthy?`

```phel
(truthy? x)
```

Checks if `x` is truthy. Same as `x == true` in PHP.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/booleans.phel#L252)

## `try`

```phel
(try expr* catch-clause* finally-clause?)
```

All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.

**Example**

```phel
(try (/ 1 0) (catch \Exception e "error"))
```

## `type`

```phel
(type x)
```

Returns the type of `x`. The following types can be returned:

* `:vector`
* `:list`
* `:struct`
* `:hash-map`
* `:set`
* `:keyword`
* `:symbol`
* `:var`
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

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L28)

## `underive`

```phel
(underive child parent)
```

Removes a parent/child relationship from the global hierarchy.

**Example**

```phel
(underive :square :shape)
```

**See also:** `derive`, `isa?`, `parents`, `ancestors`, `descendants`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L114)

## `union`

```phel
(union & sets)
```

Union multiple sets into a new one.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L16)

## `unquote`

```phel
(unquote expr)
```

Values that should be evaluated in a macro are marked with the unquote function. Shortcut: ,

**Example**

```phel
`(+ 1 ,(+ 2 3)) ; => (+ 1 5)
```

## `unquote-splicing`

```phel
(unquote-splicing expr)
```

Values that should be evaluated in a macro are marked with the unquote function. Shortcut: ,@

**Example**

```phel
`(+ ,@[1 2 3]) ; => (+ 1 2 3)
```

## `unreduced`

```phel
(unreduced x)
```

If `x` is Reduced, returns the unwrapped value; otherwise returns `x`.

**See also:** `reduced`, `reduced?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L30)

## `unset`

```phel
(unset ds key)
```

Returns `ds` without `key`.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/sequences.phel#L236)

## `unset-in`

```phel
(unset-in ds ks)
```

Removes a value from a nested data structure.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L313)

## `update`

```phel
(update ds k f & args)
```

Updates a value in a datastructure by applying `f` to the current value.

**Example**

```phel
(update {:count 5} :count inc) ; => {:count 6}
```

**See also:** `update-in`, `assoc`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L284)

## `update-in`

```phel
(update-in ds [k & ks] f & args)
```

Updates a value in a nested data structure by applying `f` to the value at path.

**Example**

```phel
(update-in {:a {:b 5}} [:a :b] inc) ; => {:a {:b 6}}
```

**See also:** `get-in`, `assoc-in`, `update`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L291)

## `update-keys`

```phel
(update-keys m f)
```

Returns a map with `f` applied to each key.

**Example**

```phel
(update-keys {:a 1 :b 2} name) ; => {"a" 1 "b" 2}
```

**See also:** `update-vals`, `keys`, `update`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L281)

## `update-vals`

```phel
(update-vals m f)
```

Returns a map with `f` applied to each value.

**Example**

```phel
(update-vals {:a 1 :b 2} inc) ; => {:a 2 :b 3}
```

**See also:** `update-keys`, `values`, `update`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/fns-sets.phel#L291)

## `uuid-nil-value`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L54)

## `uuid-nil?`

```phel
(uuid-nil? x)
```

Returns true if `x` is the nil UUID (all zeros), false otherwise.

**Example**

```phel
(uuid-nil? "00000000-0000-0000-0000-000000000000") ; => true
```

**See also:** `uuid?`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L69)

## `uuid-regex`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L12)

## `uuid-variant`

```phel
(uuid-variant x)
```

Returns a keyword describing the variant field of UUID `x`: `:ncs`,
  `:rfc-4122`, `:microsoft`, or `:reserved`. Returns nil if `x` is not a
  canonical UUID.

**Example**

```phel
(uuid-variant "550e8400-e29b-41d4-a716-446655440000") ; => :rfc-4122
```

**See also:** `uuid?`, `uuid-version`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L86)

## `uuid-version`

```phel
(uuid-version x)
```

Returns the version digit (1-5) encoded in UUID `x`, or nil if `x` is
  not a canonical UUID.

**Example**

```phel
(uuid-version "550e8400-e29b-41d4-a716-446655440000") ; => 4
```

**See also:** `uuid?`, `uuid-variant`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L77)

## `uuid=`

```phel
(uuid= a b)
```

Returns true if `a` and `b` are canonical UUID strings that compare
  equal case-insensitively. Returns false if either argument is not a
  valid UUID.

**Example**

```phel
(uuid= "550E8400-E29B-41D4-A716-446655440000"
         "550e8400-e29b-41d4-a716-446655440000") ; => true
```

**See also:** `uuid?`, `parse-uuid`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L57)

## `uuid?`

```phel
(uuid? x)
```

Returns true if `x` is a canonical UUID string (36 characters,
  `8-4-4-4-12` hexadecimal groups), false otherwise. PHP has no UUID
  type, so UUIDs are represented as strings.

**Example**

```phel
(uuid? "550e8400-e29b-41d4-a716-446655440000") ; => true
```

**See also:** `random-uuid`, `parse-uuid`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/uuid.phel#L15)

## `val`

```phel
(val entry)
```

Returns the value of a map entry (a two-element vector `[key value]`).

**Example**

```phel
(val (first (pairs {:a 1}))) ; => 1
```

**See also:** `key`, `vals`, `pairs`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L636)

## `vals`

```phel
(vals coll)
```

Returns a sequence of all values in a map, or `nil` when the map is `nil`
  or empty.

**Example**

```phel
(vals {:a 1 :b 2}) ; => (1 2)
(vals nil) ; => nil
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L621)

## `values`

```phel
(values coll)
```

Returns a sequence of all values in a map.

**Example**

```phel
(values {:a 1 :b 2}) ; => (1 2)
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L643)

## `var`

```phel
(var value)
```

Creates a new variable with the given value.

**Example**

```phel
(def counter (var 0))
```

**See also:** `set!`, `deref`, `swap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L30)

## `var?`

```phel
(var? x)
```

Checks if the given value is a variable.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/atoms.phel#L44)

## `vary-meta`

Returns an object with (apply f (meta obj) args) as its new metadata.

**See also:** `meta`, `with-meta`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/meta.phel#L77)

## `vec`

```phel
(vec coll)
```

Coerces a collection to a vector. For hash-maps and structs, entries
  are returned as 2-element `[key value]` vectors, matching Clojure.

**Example**

```phel
(vec {:a 1 :b 2}) ; => [[:a 1] [:b 2]]
```

**See also:** `vector`, `set`, `into`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L239)

## `vector`

```phel
(vector & xs)
```

Creates a new vector. If no argument is provided, an empty vector is created.

**Example**

```phel
(vector 1 2 3) ; => [1 2 3]
```

## `vector?`

```phel
(vector? x)
```

Returns true if `x` is a vector, false otherwise.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/predicates.phel#L245)

## `volatile!`

```phel
(volatile! val)
```

Creates a volatile mutable reference with initial value `val`.
  Use for transducer state that needs fast mutation without watches.

**See also:** `vreset!`, `vswap!`, `var`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L103)

## `volatile?`

```phel
(volatile? x)
```

Returns true if `x` is a Volatile.

**See also:** `volatile!`, `vreset!`, `vswap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L123)

## `vreset!`

```phel
(vreset! vol val)
```

Sets the value of volatile `vol` to `val`. Returns `val`.

**See also:** `volatile!`, `vswap!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L110)

## `vswap!`

```phel
(vswap! vol f & args)
```

Applies `f` to the current value of volatile `vol` plus `args`,
  and sets the new value. Returns the new value.

**See also:** `volatile!`, `vreset!`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/transducers.phel#L116)

## `when`

```phel
(when test & body)
```

Evaluates body if test is true, otherwise returns nil.

**Example**

```phel
(when (> 10 5) "greater") ; => "greater"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L19)

## `when-first`

```phel
(when-first bindings & body)
```

Binds name to the first element of coll. When the collection is non-empty
  (first returns non-nil), evaluates body with the binding.

**See also:** `when-some`, `first`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L625)

## `when-let`

```phel
(when-let bindings & body)
```

When test is true, evaluates body with binding-form bound to the value of test

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L575)

## `when-not`

```phel
(when-not test & body)
```

Evaluates body if test is false, otherwise returns nil.

**Example**

```phel
(when-not (empty? [1 2 3]) "has items") ; => "has items"
```

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/control.phel#L25)

## `when-some`

```phel
(when-some bindings & body)
```

Binds name to the value of test. When test is not nil, evaluates body with
  binding-form bound to the value of test. Unlike when-let, false and 0 are not
  treated as falsy — only nil causes the body to be skipped.

**See also:** `when-let`, `if-some`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/protocols.phel#L617)

## `with-meta`

Returns `obj` with the given metadata `meta` attached.

**See also:** `meta`, `vary-meta`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/meta.phel#L64)

## `with-output-buffer`

```phel
(with-output-buffer & body)
```

Everything that is printed inside the body will be stored in a buffer.
   The result of the buffer is returned.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/io.phel#L18)

## `zero?`

```phel
(zero? x)
```

Checks if `x` is zero.

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/math.phel#L186)

## `zipcoll`

```phel
(zipcoll a b)
```

Creates a map from two sequential data structures. Returns a new map.

**Example**

```phel
(zipcoll [:a :b :c] [1 2 3]) ; => {:a 1 :b 2 :c 3}
```

**See also:** `zipmap`, `interleave`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L1002)

## `zipmap`

```phel
(zipmap keys vals)
```

Returns a new map with the keys mapped to the corresponding values.

  Stops when the shorter of `keys` or `vals` is exhausted.
  Works safely with infinite lazy sequences.

**Example**

```phel
(zipmap [:a :b :c] [1 2 3]) ; => {:a 1 :b 2 :c 3}
```

**See also:** `zipcoll`

[source](https://github.com/phel-lang/phel-lang/blob/main/src/phel/core/seq-fns.phel#L987)

