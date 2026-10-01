---
description: Evaluate a Phel expression with ./bin/phel eval.
argument-hint: "<phel expression>"
disable-model-invocation: true
x-claude:
  allowed-tools: "Bash(./bin/phel *), Bash(echo *), Bash(printf *)"
x-codex:
  interface:
    display_name: Phel REPL
---

# Phel REPL

Evaluate Phel expressions to verify behavior without writing test files.

## Instructions

1. Take the expression from the argument (or ask for one if empty).

2. Evaluate it using the Phel CLI `eval` command:
   ```bash
   ./bin/phel eval '<expression>'
   ```

   Or read the expression from stdin with `-`:
   ```bash
   echo '<expression>' | ./bin/phel eval -
   ```

   For multi-form snippets that need a namespace, write a temp file:
   ```bash
   printf '%s\n' '(ns repl-test)' '<expression>' > "${TMPDIR:-/tmp}/phel-repl-test.phel"
   ./bin/phel run "${TMPDIR:-/tmp}/phel-repl-test.phel"
   ```

3. Report the result. If there's an error, explain what went wrong.

## Examples

```
/phel-repl (+ 1 2)
/phel-repl (map inc [1 2 3])
/phel-repl (defn greet [name] (str "Hello, " name "!"))
```
