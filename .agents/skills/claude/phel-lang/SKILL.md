---
name: phel-lang
description: Building applications WITH Phel (Lisp that compiles to PHP). Triggers on .phel files, phel-config.php, phel CLI commands (init, run, repl, test, build), or requests to build a Phel app. Skip when working on the Phel compiler internals (use compiler-guide or phel-patterns instead).
---

# Phel

Lisp dialect that compiles to PHP. PHP interop via `php/` prefix.

## Load order

1. `.agents/RULES.md` — hard rules and CLI cheatsheet
2. `.agents/index.md` — task map
3. `.agents/tasks/<intent>.md` — recipe for the current task
4. `src/phel/` and `docs/` only when a recipe points there

## Working examples

`.agents/examples/{todo-app, http-json-api, cli-wordcount}/` — copy, adapt, run.
