---
description: Phel language conventions for source and test files
globs: src/phel/**,tests/phel/**
---

# Phel Conventions

## Naming

- kebab-case for functions and variables: `my-function`, `my-variable`
- `defn-` for private functions (not exported)
- Namespace names match directory structure: `phel.core`, `phel.string`

## Docstrings

Every public function should have metadata:
- `:doc`: description of what the function does
- `:see-also`: related functions as strings, e.g. `["map" "filter"]`
- `:example`: inline usage example

## Comments

- Use `;` for line comments
- `;;` for standalone line comments, `;` for inline comments after code
- `#_` to comment out the next form

## Tests

- A test file under `tests/phel/<path>.phel` declares `(ns phel-test.<path> (:require phel.test :refer [deftest is]))`.
- Fixture namespaces under `tests/phel/fixtures/` are also run on their own by `phel test --parallel`, so each one requires every namespace it uses.
- Run one file with `./bin/phel test tests/phel/<file>`. Evaluate a form with `./bin/phel eval '<form>'`.

## Semantics

- Follow Clojure-aligned semantics where possible
- Prefer `conj` over `put` for collection operations
- Use `defstruct` for data types, not PHP classes
- Prefer `php-indexed-array` / `php-associative-array` over direct `php/array` construction. Raw construction is reserved for core bootstrap code that loads before `core/arrays`, the constructor implementations themselves, and measured higher-order paths where applying the wrapper would add runtime cost.

## Formatting

The post-edit hook runs `./bin/phel format` on every edited `*.phel` file. Check without writing: `./bin/phel format --dry-run <file>`.

## Commas

Commas are optional whitespace. Match Clojure:

- Use them **between key/value pairs of a single-line map** to group visually: `{:a 1, :b 2}`.
- Multi-line maps: no commas (the newline separates pairs).
- Not in vectors, lists, or function calls.
- `,` is whitespace everywhere, quasiquote included. `~` is unquote and `~@` unquote-splicing.
- `phel format` preserves commas but never inserts them (like cljfmt): they are author's choice.
