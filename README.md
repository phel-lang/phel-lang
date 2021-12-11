<p align="center">
  <img src="logo_readme.svg" width="350" alt="Phel logo"/>
</p>

<p align="center">
  <a href="https://github.com/phel-lang/phel-lang/actions">
    <img src="https://github.com/phel-lang/phel-lang/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=master">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=master">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/coverage.png?b=master" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/phel-lang/phel-lang">
    <img src="https://shepherd.dev/github/phel-lang/phel-lang/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
  <a href="https://gitter.im/phel-lang/community?utm_source=badge&amp;utm_medium=badge&amp;utm_campaign=pr-badge">
    <img src="https://badges.gitter.im/Join%20Chat.svg" alt="Gitter">
  </a>
</p>

---

[Phel](https://phel-lang.org/) is a functional programming language that compiles to PHP.
It is a dialect of [Lisp](https://en.wikipedia.org/wiki/Lisp_(programming_language)) inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

#### Example
<!-- using "clojure" here is just for the md coloring -->
```clojure
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

## Documentation

The documentation for Phel can be found on the Phel's website: [https://phel-lang.org](https://phel-lang.org).

## Community

Feel free to ask questions and join discussions on the [Phel Gitter channel](https://gitter.im/phel-lang/community).

## Contribute

Please refer to [CONTRIBUTING.md](https://github.com/phel-lang/phel-lang/blob/master/.github/CONTRIBUTING.md) for information on how to contribute to Phel.
