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
(ns my\example)

(def my-name "world")

(defn print-name [your-name]
  (print "hello" your-name))

(print-name my-name)
```

## Getting started

Phel requires PHP 7.4 or higher and Composer. Read the [Getting Started Guide](/getting-started) to create your first Phel programm.