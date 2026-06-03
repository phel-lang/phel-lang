---
description: Phel language conventions for source and test files
globs: src/phel/**,tests/phel/**
---

# Phel Conventions

## Naming

- kebab-case for functions and variables: `my-function`, `my-variable`
- `defn-` for private functions (not exported)
- Namespace names match directory structure: `phel\core`, `phel\string`

## Docstrings

Every public function should have metadata:
- `:doc` — description of what the function does
- `:see-also` — related functions (as strings): `["map" "filter"]`
- `:example` — inline usage example

## Comments

- Use `;` for line comments (not `#`)
- `;;` for standalone line comments, `;` for inline comments after code
- `#| |#` for multiline comments, `#_` to comment out a form

## Semantics

- Follow Clojure-aligned semantics where possible
- Prefer `conj` over `put` for collection operations
- Use `defstruct` for data types, not PHP classes

## Macros

Editing a `defmacro` body or `` ` `` quasiquote? Load [macro-hygiene.md](macro-hygiene.md) first — local `let` names can silently shadow globals.

## Formatting

`*.phel` files auto-formatted by `.claude/hooks/format-phel.sh` after Edit/Write (runs `./bin/phel format <file>`). No manual run needed. Check without writing: `./bin/phel format --dry-run <file>`.

## Commas

Commas are optional whitespace — match Clojure:

- Use them **between key/value pairs of a single-line map** to group visually: `{:a 1, :b 2}`.
- Multi-line maps: no commas (the newline separates pairs).
- Not in vectors, lists, or function calls.
- `,` is **unquote** inside a `` ` `` quasiquote, so never add commas to quasiquoted maps.
- `phel format` preserves commas but never inserts them (like cljfmt) — they are author's choice.
