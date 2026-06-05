---
description: Evaluate Phel expressions to verify behavior. Use when you need to test Phel code interactively.
argument-hint: "<phel expression>"
disable-model-invocation: true
allowed-tools: "Bash(./bin/phel *), Bash(echo *), Bash(timeout *)"
---

# Phel REPL

Evaluate Phel expressions to verify behavior without writing test files.

## Instructions

1. Take the expression from `$ARGUMENTS` (or ask for one if empty).

2. Evaluate it using the Phel CLI `eval` command:
   ```bash
   timeout 10 ./bin/phel eval '$ARGUMENTS'
   ```

   Or read the expression from stdin with `-`:
   ```bash
   echo '$ARGUMENTS' | timeout 10 ./bin/phel eval -
   ```

   For multi-form snippets that need a namespace, write a temp file:
   ```bash
   echo '(ns repl-test) $ARGUMENTS' > /tmp/phel-repl-test.phel
   timeout 10 ./bin/phel run /tmp/phel-repl-test.phel
   ```

3. Report the result. If there's an error, explain what went wrong.

## Examples

```
/phel-repl (+ 1 2)
/phel-repl (map inc [1 2 3])
/phel-repl (defn greet [name] (str "Hello, " name "!"))
```
