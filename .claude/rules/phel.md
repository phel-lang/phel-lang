---
description: Phel language conventions
globs: src/phel/**,tests/phel/**
---

# Phel Conventions

## Naming

- Use kebab-case for functions and variables: `my-function`, `my-variable`
- Namespace names match directory structure: `phel\core`, `phel\str`

## Docstrings

- Use `:doc` metadata for documentation
- Use `:see-also` to reference related functions (as strings)
- Use `:example` to provide inline usage examples

## Semantics

- Follow Clojure-aligned semantics where possible
- Prefer `conj` over `put` for collection operations
