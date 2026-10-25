---
description: Run, baseline or compare benchmarks.
argument-hint: "[run|baseline|compare|filter]"
disable-model-invocation: true
allowed-tools:
  - "read"
  - "shell(composer *)"
  - "shell(./vendor/bin/phpbench *)"
  - "shell(./bin/phel *)"
---

# Benchmark Runner

## Context

Run first: `git branch --show-current`, `git log --oneline -1`.

## Instructions

1. If the argument is empty or `run`:
   ```bash
   composer phpbench
   ```

2. If the argument is `baseline`:
   ```bash
   composer phpbench-base
   ```
   Report that future `/benchmark compare` will diff against this snapshot.

3. If the argument is `compare`:
   ```bash
   composer phpbench-ref
   ```
   Treat a difference above 5% as a candidate regression or improvement. Rerun it and report noise before drawing a conclusion.

4. If the argument looks like a class or method filter:
   ```bash
   ./vendor/bin/phpbench run --filter="<argument>" --report=aggregate --ansi
   ```

5. For Phel code (`defbench`), use `./bin/phel bench`. To compare the working tree against a git ref, use `./bin/phel bench --ab=<ref> --pairs=N`. See `docs/benchmarking.md`.

6. Report results focusing on:
   - Baseline commit, comparison commit or working-tree state, and method per `docs/benchmarking.md`
   - Mean execution time and memory usage
   - Regressions vs improvements (when comparing)
   - Which benchmark classes were affected
