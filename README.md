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

Phel is a functional, [Lisp](https://en.wikipedia.org/wiki/Lisp_(programming_language))-inspired programming language that compiles to PHP. It brings the expressive power of [Clojure](https://clojure.org/) and the simplicity of [Janet](https://janet-lang.org/) to the PHP ecosystem — enabling you to write concise, immutable, and composable code that runs anywhere PHP does.

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

### Getting Started
- [Quick Start Tutorial](docs/quickstart.md)
  Get up and running in 5 minutes with your first Phel application.
- [Installation](https://phel-lang.org/documentation/getting-started/)
  Detailed installation guide and project setup.

### Learning Resources
- [Common Patterns](docs/patterns.md)
  Idiomatic Phel code patterns for everyday tasks.
- [PHP/Phel Interop](docs/php-interop.md)
  Complete guide to working between PHP and Phel code.
- [Reader Shortcuts](docs/reader-shortcuts.md)
  Reference for all special syntax and reader macros.
- [Lazy Sequences](docs/lazy-sequences.md)
  Performance patterns and common pitfalls.
- [Mocking Guide](docs/mocking-guide.md)
  Testing with mocks and test doubles.
- [Examples](docs/examples/README.md)
  Runnable code samples covering key features.

### Reference
- [Website](https://phel-lang.org)
  Official website with tutorials, exercises, and blog posts.
- [Packagist](https://packagist.org/packages/phel-lang/phel-lang)
  Official PHP package repository.
- [Internals](docs/internals/compiler.md)
  Deep dive into the compiler architecture.

## Build PHAR

Run the following command to create a standalone PHAR executable:

```sh
./build/phar.sh
```

The generated `build/out/phel.phar` can then be executed directly.

## Contribute

Please refer to [CONTRIBUTING.md](https://github.com/phel-lang/phel-lang/blob/main/.github/CONTRIBUTING.md) for information on how to contribute to Phel. For a quick overview of project layout, tooling, and review expectations, visit the [Repository Guidelines](AGENTS.md).
