---
description: Evaluate Phel expressions to verify behavior. Use when you need to test Phel code interactively.
argument-hint: "<phel expression>"
disable-model-invocation: true
x-claude:
  allowed-tools: "Bash(./bin/phel *), Bash(echo *), Bash(printf *)"
---

# Phel REPL

Evaluate Phel expressions to verify behavior without writing test files.

## Instructions

1. Take the expression from `$ARGUMENTS` (or ask for one if empty).

2. Evaluate it using the Phel CLI `eval` command:
   ```bash
   ./bin/phel eval '$ARGUMENTS'
   ```

   Or read the expression from stdin with `-`:
   ```bash
   echo '$ARGUMENTS' | ./bin/phel eval -
   ```

   For multi-form snippets that need a namespace, write a temp file:
   ```bash
   printf '%s\n' '(ns repl-test)' '$ARGUMENTS' > "${TMPDIR:-/tmp}/phel-repl-test.phel"
   ./bin/phel run "${TMPDIR:-/tmp}/phel-repl-test.phel"
   ```

3. Report the result. If there's an error, explain what went wrong.

## Examples

```
/phel-repl (+ 1 2)
/phel-repl (map inc [1 2 3])
/phel-repl (defn greet [name] (str "Hello, " name "!"))
```
