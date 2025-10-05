<p align="center">
  <img src="logo_readme.svg" width="350" alt="Phel logo"/>
</p>

<p align="center">
  <a href="https://github.com/phel-lang/phel-lang/actions">
    <img src="https://github.com/phel-lang/phel-lang/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=main">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/quality-score.png?b=main" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=main">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/coverage.png?b=main" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/phel-lang/phel-lang">
    <img src="https://shepherd.dev/github/phel-lang/phel-lang/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
  <a href="https://deepwiki.com/phel-lang/phel-lang">
    <img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki">
  </a>
</p>


---

[Phel](https://phel-lang.org/) is a functional programming language that compiles to PHP.

It is a dialect of [Lisp](https://en.wikipedia.org/wiki/Lisp_(programming_language)) inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/), designed for building robust applications in the PHP ecosystem.

#### Example
<!--
using "clojure" here is just for the md coloring
we should use "phel" once GitHub accept phel coloring too
-->
```clojure
; Define a namespace
(ns my\example)

; Define a variable with name "my-name" and value "world"
(def my-name "world")

; Define a function with name "print-name" and one argument "your-name"
(defn print-name [your-name]
  (print "hello" your-name))

; Call the function
(print-name my-name)
```

## Documentation

- [Website](https://phel-lang.org)
  - Features, documentation, exercises and blog
- [Installation](https://phel-lang.org/documentation/getting-started/)
  - Quick start with scaffolding or manual installation
- [Packagist](https://packagist.org/packages/phel-lang/phel-lang)
  - The PHP Package Repository
- [Internals](docs/internals/compiler.md)
  - Additional documentation about the compiler internals
- [Examples](docs/examples/README.md)
  - Ten progressively complex scripts that you can run with the Phel CLI

## Build PHAR

Run the following command to create a standalone PHAR executable:

```sh
./build/phar.sh
```

The generated `build/out/phel.phar` can then be executed directly.

## Contribute

Please refer to [CONTRIBUTING.md](https://github.com/phel-lang/phel-lang/blob/main/.github/CONTRIBUTING.md) for information on how to contribute to Phel.
