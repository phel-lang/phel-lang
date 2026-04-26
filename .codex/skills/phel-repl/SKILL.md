---
name: phel-repl
description: Evaluate Phel expressions and snippets. Use when Codex needs to verify Phel behavior interactively with ./bin/phel, run a one-off expression, or explain a Phel evaluation error.
---

# Phel REPL

## Workflow

1. Use the expression from the user or task context. If none is available, ask for it.

2. Try direct evaluation:
   ```bash
   echo '<expression>' | timeout 10 ./bin/phel run --eval
   ```

3. If `--eval` is unavailable, create a temporary file outside the repo:
   ```bash
   printf '%s\n' '(ns repl-test)' '<expression>' >/tmp/phel-repl-test.phel
   timeout 10 ./bin/phel run /tmp/phel-repl-test.phel
   ```

4. Report the result. For errors, include the relevant message and the likely cause.

## Examples

```phel
(+ 1 2)
(map inc [1 2 3])
(defn greet [name] (str "Hello, " name "!"))
```
