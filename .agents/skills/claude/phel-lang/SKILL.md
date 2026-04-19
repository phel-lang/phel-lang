---
name: phel-lang
description: Use when building applications WITH Phel (Lisp compiling to PHP). Triggers on mentions of Phel, .phel files, phel-config.php, phel init/run/repl/test/build, or "lisp + php". Loads agent docs index for task-specific guidance. Skip when working on the Phel compiler internals (use `compiler-guide` or `phel-patterns` instead).
---

# Phel Language Skill

Phel is a Lisp dialect that compiles to PHP. Clojure-influenced syntax and semantics, PHP interop via `php/` prefix.

## Load first

Always read these before coding:

1. `.agents/index.md` — task-based reading map
2. `.agents/README.md` — scope + sync policy

## Core workflow

For any Phel coding task:

1. **Scaffold if empty**: `./vendor/bin/phel init [name] [--flat|--minimal]`. See `.agents/tasks/scaffold-project.md`.
2. **Check unknowns in REPL**: `./vendor/bin/phel repl`. Use `(doc <fn>)` before guessing signatures.
3. **Reference docs by intent** via `.agents/index.md` table.
4. **Code** under `src/phel/<ns>.phel`.
5. **Test** with `phel\test` (`deftest`, `is`). Run `./vendor/bin/phel test`.

## Hard rules

- **Verify fn names**: `(doc <fn>)` or grep `src/phel/core/` before using. Don't invent.
- **Immutability**: `(conj v x)` returns new vec; `v` unchanged. Rebind with `def`/`let`.
- **Build safety**: top-level side effects break `phel build`. Guard: `(when-not *build-mode* (main))`.
- **PHP interop**: `php/fn-name`, `(php/-> obj (method args))`, `(php/:: Class (static args))`, `(php/new Class args)`.
- **Threading**: `->` (first arg) for map/object ops, `->>` (last arg) for collection pipelines.
- **Truthiness**: only `false` and `nil` are falsy. `0`, `""`, `[]` are truthy.
- **Namespaces**: need ≥ 2 segments (`app\main`, not `main`).
- **Comments**: `;` inline, `;;` standalone, `#_` form-comment, `#| |#` block.
- **Parens count**: every fn call, every special form. `map square [1 2 3]` is not a call.

## Task recipes

- New project → `.agents/tasks/scaffold-project.md`
- HTTP app / API → `.agents/tasks/http-app.md`
- (more recipes land in phase 2)

## Key docs (ground truth)

| Need | File |
|------|------|
| Syntax fast | `docs/quickstart.md` |
| Idioms | `docs/patterns.md` |
| PHP interop | `docs/php-interop.md` |
| Symfony/Laravel embedding | `docs/framework-integration.md` |
| Examples | `docs/examples/*.phel` |
| Reader shortcuts | `docs/reader-shortcuts.md` |
| Data structures | `docs/data-structures-guide.md` |

## CLI cheatsheet

| Task | Command |
|------|---------|
| Scaffold | `./vendor/bin/phel init [name] [--flat\|--minimal]` |
| Run | `./vendor/bin/phel run <file>` |
| Eval | `./vendor/bin/phel eval '<expr>'` |
| REPL | `./vendor/bin/phel repl` |
| Test | `./vendor/bin/phel test [path]` |
| Build AOT | `./vendor/bin/phel build` |
| Doc | `./vendor/bin/phel doc <fn>` |
| Format | `./vendor/bin/phel format <file>` |

## Common gotchas

- Forgot parens around fn call → not a call, silent bug
- Mutated-in-place expectation → rebind needed
- Used `.method obj` → use `(php/-> obj (method))` or shorthand `(.method obj)`
- Used `{key val}` as PHP assoc array → need `#php {"k" "v"}` or `(php-associative-array ...)`
- Top-level `(main)` blocks `phel build` → wrap in `(when-not *build-mode* ...)`
- `require` of unknown ns → namespace must match file path under configured src dir
