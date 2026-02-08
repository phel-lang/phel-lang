---
description: Phel language conventions for source and test files
globs: src/phel/**,tests/phel/**
---

# Phel Conventions

## Naming

- kebab-case for functions and variables: `my-function`, `my-variable`
- `defn-` for private functions (not exported)
- Namespace names match directory structure: `phel\core`, `phel\str`

## Docstrings

Every public function should have metadata:
- `:doc` — description of what the function does
- `:see-also` — related functions (as strings): `["map" "filter"]`
- `:example` — inline usage example

## Semantics

- Follow Clojure-aligned semantics where possible
- Prefer `conj` over `put` for collection operations
- Use `defstruct` for data types, not PHP classes
