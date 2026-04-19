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

A functional, Lisp-inspired language that compiles to PHP. Inspired by Clojure, Phel brings macros, persistent data structures, and expressive functional idioms to the PHP ecosystem.

#### Example
<!--
using "clojure" here is just for the md coloring
we should use "phel" once GitHub accept phel coloring too
-->
```clojure
(ns my\example)

(defn greet [name] (str "Hello, " name "!"))

(println (greet "Phel"))
;; => Hello, Phel!
```

<details>
<summary><b>More examples →</b></summary>

<table>
<tr>
<td width="50%" valign="top">

**Data pipeline**

```clojure
(def users
  [{:name "Ada" :age 36}
   {:name "Bob" :age 17}
   {:name "Cam" :age 41}])

(->> users
     (filter #(>= (:age %) 18))
     (map :name)
     sort)
;; => ["Ada" "Cam"]
```

</td>
<td width="50%" valign="top">

**HTTP response**

```clojure
(ns app (:require phel\http :as h))

(def req (h/request-from-globals))

(h/emit-response
  (h/response-from-map
    {:status 200
     :headers {"Content-Type" "text/plain"}
     :body (str "Hello " (:uri req))}))
```

</td>
</tr>
<tr>
<td valign="top">

**Macros**

```clojure
(defmacro unless [pred & body]
  `(if (not ,pred)
     (do ,@body)))

(unless (zero? 1)
  (println "not zero"))
;; => not zero

(unless false (println "ok"))
;; => ok
```

</td>
<td valign="top">

**PHP interop**

```clojure
(ns app)

(def now (php/new \DateTime))
(.format now "Y-m-d")
;; => "2026-04-20"

(def epoch (php/new \DateTime "1970-01-01"))
(.-days (.diff now epoch))
;; => 20564
```

</td>
</tr>
</table>
</details>

## Getting Started

Install Phel in any PHP project and scaffold a ready-to-run app in under a minute:

```sh
composer require phel-lang/phel-lang
./vendor/bin/phel init
```

That creates `phel-config.php`, `src/phel/main.phel`, and a matching `tests/phel/main_test.phel`. Then:

```sh
./vendor/bin/phel run src/phel/main.phel   # run your code
./vendor/bin/phel test                     # run the tests
./vendor/bin/phel repl                     # poke at it interactively
./vendor/bin/phel build                    # compile to PHP for production
```

Need a one-off snippet or shell pipeline? `phel eval` runs inline expressions and `phel eval -` reads the program from stdin:

```sh
./vendor/bin/phel eval '(+ 1 2)'           # prints 3
echo '(println "hi")' | ./vendor/bin/phel eval -
./vendor/bin/phel eval - < script.phel
```

For a single-file experiment or scratch project, use the root layout:

```sh
./vendor/bin/phel init --minimal
```

You get a single `main.phel` + `main_test.phel` + a one-line `phel-config.php` at the repo root, no subdirectories.

## Documentation

### Getting Started
- [Quick Start Tutorial](docs/quickstart.md)
  Get up and running in 5 minutes with your first Phel application.
- [Installation](https://phel-lang.org/documentation/getting-started/)
  Detailed installation guide and project setup.

### Learning Resources
- [Clojure Migration Guide](docs/clojure-migration.md)
  Coming from Clojure? Key differences, interop cheat sheet, and what's the same.
- [Common Patterns](docs/patterns.md)
  Idiomatic Phel code patterns for everyday tasks.
- [PHP/Phel Interop](docs/php-interop.md)
  Complete guide to working between PHP and Phel code.
- [Reader Shortcuts](docs/reader-shortcuts.md)
  Reference for all special syntax and reader macros.
- [Reader Conditionals](docs/reader-conditionals.md)
  Cross-platform code with `#?()`, `#?@()`, and `.cljc` files.
- [Transducers](docs/transducers.md)
  Composable transformation pipelines without intermediate collections.
- [Data Structures](docs/data-structures-guide.md)
  Guide to Phel's persistent, immutable collections.
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
- [Repository Guidelines](AGENTS.md)
  Project structure, modules, build commands, and review expectations.

## Build PHAR

Run the following command to create a standalone PHAR executable:

```sh
./build/phar.sh
```

The generated `build/out/phel.phar` can then be executed directly.

## Contribute

| Resource | What's there |
|----------|-------------|
| [CONTRIBUTING.md](.github/CONTRIBUTING.md) | Setup, workflow, testing, and PR guidelines |
| [Repository Guidelines](AGENTS.md) | Architecture, modules, build commands, review expectations |
| [docs/](docs/) | Guides, examples, and compiler internals |
| [phel-lang.org](https://phel-lang.org) | Tutorials, exercises, and blog posts |

New here? Start with [CONTRIBUTING.md](.github/CONTRIBUTING.md): explains the two-language codebase and has a "Where to Start" section based on your interests.
