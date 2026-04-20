# Phel rules + CLI

Single source for every skill adapter.

## Rules

1. Verify fn names with `(doc <fn>)` or grep `src/phel/core/`. No invention.
2. Collections immutable. `(conj v x)` returns new; rebind with `def`/`let`, or use `atom`.
3. Top-level side effects break `phel build`. Guard with `(when-not *build-mode* ...)`.
4. PHP interop: `(php/fn args)`, `(php/-> obj (method args))`, `(php/:: Class (static args))`, `(php/new Class args)`. Shorthands: `(.method obj args)`, `(.-prop obj)`, `(Class/method args)`, `Class/CONST`.
5. Threading: `->` first-arg, `->>` last-arg, `some->` / `some->>` nil-safe, `cond->` conditional.
6. Only `false` and `nil` are falsy. `0`, `""`, `[]` truthy.
7. Namespaces need ≥ 2 segments (`app\main`). File path matches ns under src dir.
8. Comments: `;` inline, `;;` standalone, `#_` form, `#| |#` block.
9. PHP assoc array: `#php {"k" "v"}` or `(to-php-array m)`. Not `{:k "v"}`.
10. Catch PHP: `(catch php\SomeException e ...)`.

## CLI

| Task | Command |
|------|---------|
| Scaffold | `./vendor/bin/phel init [name] [--nested\|--minimal]` |
| Run | `./vendor/bin/phel run <file>` |
| Eval | `./vendor/bin/phel eval '<expr>'` |
| REPL | `./vendor/bin/phel repl` |
| Test | `./vendor/bin/phel test [path]` |
| Build | `./vendor/bin/phel build` |
| Doc | `./vendor/bin/phel doc <fn>` |
| Format | `./vendor/bin/phel format <file>` |
| Install skill | `./vendor/bin/phel agent-install <platform>\|--all` |

## Workflow

1. `phel init` if empty.
2. Unknowns → REPL `(doc <fn>)` before guessing.
3. Code `src/<ns>.phel` (flat) or `src/phel/<ns>.phel` (`--nested`).
4. `phel\test`: `deftest`, `is`. Run `phel test`.
5. `phel run` or web entry.

## Commits

Conventional (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). No AI/LLM references.
