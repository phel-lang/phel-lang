+++
title = "Testing"
weight = 16
+++

Phel comes with an integrated unit testing framework.

## Assertions

The core of the libray is the `is` macro, which can be used to defined assertions.

```phel
(is (= 4 (+ 2 2)) "my test description")
(is (true? (or true false)) "my othe test")
```

The first argument of the `is` macro must be in one of the following forms. The second argument is an optional string to describe the test.

```phel
(predicate expected actual)
# Example: (is (= 4 (+ 2 2)))
```

This tests whether, according to `predicate`, the `actual` value is in fact what we `expected`.

```phel
(predicate value)
# Example: (is (true? (or true false)))
```
This tests whether the `value` satisfies the `predicate`.

```phel
(not (predicate expected actual))
# Example: (is (not (= 4 (+ 2 3))))
```

This tests whether, according to `predicate`, the `actual` value is **not** what we `expected`.

```phel
(not (predicate value))
# Example (is (not (true? (and true false))))
```
This tests whether the `value` does **not** satisfies the `predicate`.

```phel
(thrown? exception-type body)
# Example: (is (thrown? \Exception (throw (php/new \Exception "test"))))
```
This tests whether the execution of `body` throws an exception of type `exception-type`.

```phel
(thrown-with-msg? exception-type msg body)
# Example: (is (thrown? \Exception "test"  (throw (php/new \Exception "test"))))
```
This tests whether the execution of `body` throws an exception of type `exception-type` and that the exception has the message `msg`.

```phel
(output? expected body) # For example (output? "hello" (php/echo "hello"))
```
This tests whether the execution of `body` prints the `expected` text to the output stream.

## Defining tests

Test can be defined by using the `deftest` macro. This macro is like a function without arguments.

```phel
(ns my-namespace\tests
  (:require phel\test :refer [deftest is]))

(deftest my-test
  (is (= 4 (+ 2 2))))
```

## Running tests

Tests can be run using the `./vendor/bin/phel test` command. Therefore, the `test` configuration entry must be set (see [Configuration](/documentation/configuration/)).

I can use want to run the test manually on your own, the `run-tests` function can be used.  As arguments it takes a list of namespaces that should be tested.

```phel
(run-tests 'my\ns\a 'my\ns\b)
```
