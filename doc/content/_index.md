+++
title="The Phel Language"
+++

Phel is a functional programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

## Community

Feel free to ask questions and join discussions on the [Phel Gitter channel](https://gitter.im/phel-lang/community).

## Features

* Built on PHP's ecosystem
* Good error reporting
* Persistent Datastructures (Lists, Vectors, Maps and Sets)
* Macros
* Recursive functions
* Powerful but simple Syntax
* REPL

## Why Phel?

Phel is a result of my [failed attempts to do functional programming in PHP](/blog/functional-programming-in-php). Basically I wanted:

* A LISP-inspired
* functional programming language
* that runs on cheap hosting providers
* and is easy to write and debug


## Example

The following example gives a short impression on how Phel looks like:

```phel
# Define a namespace
(ns my\example)

# Define a variable with name "my-name" and value "world"
(def my-name "world")

# Define a function with name "print-name" and one argument "your-name"
(defn print-name [your-name]
  (print "hello" your-name))

# Call the function
(print-name my-name)
```

## Getting started

Phel requires PHP 7.4 or higher and Composer. Read the [Getting Started Guide](/documentation/getting-started) to create your first Phel program.


## Status of Development

Phel is fairly complete but not marked as ready. We still want to envolve this language without thinking to much about breaking changes. Maybe some of you are willing to test it out and give feedback.
