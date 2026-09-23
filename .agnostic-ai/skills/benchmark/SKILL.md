---
description: Run performance benchmarks, create baselines, and compare results
argument-hint: "[run|baseline|compare|filter]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(composer *), Bash(./vendor/bin/phpbench *), Bash(./bin/phel *)"
---

# Benchmark Runner

## Context

!`git branch --show-current`
!`git log --oneline -1`

## Instructions

1. If `$ARGUMENTS` is empty or `run`:
   ```bash
   composer phpbench
   ```

2. If `$ARGUMENTS` is `baseline`:
   ```bash
   composer phpbench-base
   ```
   Report that future `/benchmark compare` will diff against this snapshot.

3. If `$ARGUMENTS` is `compare`:
   ```bash
   composer phpbench-ref
   ```
   Highlight any regressions (>5% slower) and improvements (>5% faster).

4. If `$ARGUMENTS` looks like a class or method filter:
   ```bash
   ./vendor/bin/phpbench run --filter="$ARGUMENTS" --report=aggregate --ansi
   ```

5. For Phel code (`defbench`), use `./bin/phel bench`. To compare the working tree against a git ref, use `./bin/phel bench --ab=<ref> --pairs=N`. See `docs/benchmarking.md`.

6. Report results focusing on:
   - Mean execution time and memory usage
   - Regressions vs improvements (when comparing)
   - Which benchmark classes were affected
