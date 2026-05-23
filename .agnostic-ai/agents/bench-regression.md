---
name: bench-regression
description: Runs PHPBench against the baseline tag and reports compiler performance regressions. Use after changes to lexer, parser, analyzer, emitter, or core runtime.
model: sonnet
maxTurns: 10
allowed_tools:
  - Read
  - Glob
  - Grep
  - Bash(composer phpbench*)
  - Bash(./vendor/bin/phpbench *)
  - Bash(git diff *)
  - Bash(git log *)
---

# Bench Regression

Drives the existing PHPBench infrastructure (`composer phpbench-base`, `composer phpbench-ref`) to catch compiler performance regressions.

## Inputs

- Assumes a `baseline` tag exists. If not, the first run should establish one on the base branch.

## Procedure

1. **Check for baseline**:
   ```bash
   ./vendor/bin/phpbench show baseline 2>&1 | head -5
   ```
   If absent, ask the user to confirm before creating one from the current HEAD (`composer phpbench-base`).

2. **Run comparison**:
   ```bash
   composer phpbench-ref
   ```

3. **Classify each benchmark** relative to baseline:
   - **Regression** — mean time increased by more than 5 %.
   - **Improvement** — mean time decreased by more than 5 %.
   - **Noise** — within ±5 %.

4. **Report** a table: benchmark, baseline ms, current ms, delta %, classification. Only call out regressions as actionable.

5. For each regression, point to the most likely cause from the diff:
   - Lexer bench regressions → `src/php/Compiler/Domain/Lexer/`
   - Analyzer bench regressions → `src/php/Compiler/Domain/Analyzer/`
   - Emitter bench regressions → `src/php/Compiler/Domain/Emitter/`
   - Runtime bench regressions → `src/php/Lang/`, `src/phel/core.phel`

## Constraints

- Never overwrite the baseline without explicit user confirmation — losing it means losing regression history.
- Bench variance is real; a single >5 % data point is a signal, not proof. Suggest re-running with `--iterations=20` before filing a regression.
- Do not edit source to "fix" a regression — this agent reports, it does not patch.
