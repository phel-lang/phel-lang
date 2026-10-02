---
name: phel-lang
description: Building applications WITH Phel (Lisp that compiles to PHP). Triggers on .phel files, phel-config.php, phel CLI commands (init, run, repl, test, build), or requests to build a Phel app. Skip when working on the Phel compiler itself.
---

# Phel

Lisp dialect compiling to PHP. PHP interop via `php/` prefix.

## Load order

1. `.agents/RULES.md` — hard rules, modern features, CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code
3. `.agents/index.md` — task map
4. `.agents/tasks/<intent>.md` — recipe for current task
5. `.agents/quick-syntax.md` — one-screen syntax cheatsheet
6. `vendor/phel-lang/phel-lang/src/phel/` and `vendor/phel-lang/phel-lang/docs/` only when a recipe points there

## Hard rules (skim, then load RULES.md)

- Verify fn names with `./vendor/bin/phel doc <fn> --format=json`, `(doc <fn>)`, or grep `vendor/phel-lang/phel-lang/src/phel/core/`. Never invent.
- Check loop after every edit: `./vendor/bin/phel lint <file> --format=json`, then `./vendor/bin/phel explain <PHELnnn>` for an unknown code, then `./vendor/bin/phel doc <fn> --format=json` for an unverified fn. Details in `.agents/RULES.md`.
- Collections immutable. `(conj v x)` returns new; rebind via `def`/`let`/`atom`.
- CLI args: `*argv*`, not `php/$argv`.
- Side effects: `doseq`; building sequences: `for`.
- String module is `phel.string` (was `phel.str` pre-v0.33).
- Namespace separator: prefer `.` (`app.main`); `\` deprecated.
- Hot or public `defn`: add `:tag` to params + return. See `.agents/tasks/typed-defn.md`.
- Opt-in `defn` metadata: `^:async`, `^:memoize`, `^{:memoize-lru N}`.
- Top-level side effects break `phel build`; guard with `(when-not *build-mode* ...)`.

## Working examples

`.agents/examples/{todo-app, http-json-api, cli-wordcount}/` — copy, adapt, run.
