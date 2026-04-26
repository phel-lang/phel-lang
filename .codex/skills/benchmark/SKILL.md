---
name: benchmark
description: Run and interpret Phel performance benchmarks. Use when Codex is asked to run phpbench, create benchmark baselines, compare benchmark results, filter benchmark classes or methods, or investigate performance regressions.
---

# Benchmark Runner

## Workflow

1. Inspect the current branch and latest commit when benchmark context matters:
   ```bash
   git branch --show-current
   git log --oneline -1
   ```

2. Choose the benchmark command from the user's request:
   - default or `run`: `composer phpbench`
   - `baseline`: `composer phpbench-base`
   - `compare`: `composer phpbench-ref`
   - class or method filter: `./vendor/bin/phpbench run --filter="<filter>" --report=aggregate --ansi`

3. For comparisons, highlight:
   - regressions more than 5% slower
   - improvements more than 5% faster
   - affected benchmark classes or methods
   - mean execution time and memory changes

4. If results look noisy, say so and recommend a rerun before treating the change as significant.
