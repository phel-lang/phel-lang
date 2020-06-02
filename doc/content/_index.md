+++
title="The Phel Language"
weight=0
sort_by = "weight"
+++

Phel is a function programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

## Features

* Built on PHP's ecosystem
* Good error reporting
* Different Datastructures (Arrays, Tables and Tuples)
* Macros
* Recursive functions
* Powerful but simple Syntax
* REPL

## Why Phel?

Why did I wrote yet another programming language? Basically I wanted:

* A LISP
* for functional programming
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

Phel requires PHP 7.4 or higher and Composer. Read the [Getting Started Guide](/getting-started) to create your first Phel programm.


## Status of Development

Phel has not been released yet. For my purposes it is running quite ok now. But there are probably a few more edge cases that I want to address before the first offical release.