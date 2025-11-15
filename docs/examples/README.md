# Phel single-file showcase

These standalone scripts demonstrate how Phel scales from a friendly first contact to
more expressive programs that lean on both functional patterns and the underlying PHP
runtime. Each file can be executed with the Phel CLI:

```bash
bin/phel run docs/examples/<file>.phel
```

## Example overview

1. **basic-types.phel** – Explore primitive values, keywords, and common collection literals.
2. **arithmetic.phel** – Crunch numbers with helper functions for common operations.
3. **control-flow.phel** – Use `cond` and `case` for branching logic.
4. **functions-recursion.phel** – Build reusable helpers and recursive algorithms.
5. **data-structures.phel** – Traverse vectors, maps, and strings with destructuring and iteration patterns.
6. **data-pipeline.phel** – Compose sequence operations with higher-order helpers and threading.
7. **macro-playground.phel** – Craft a macro to generate structured launch plans.
8. **interfaces.phel** – Model behaviour with interfaces and multiple implementations.
9. **php-integration.phel** – Interoperate with PHP classes, dates, and JSON encoding.
10. **html-rendering.phel** – Render dynamic HTML with Phel's templating macros.

Feel free to copy these files into your own project and experiment.

## Test all examples

```bash
find docs/examples -name "*.phel" -type f | \
    sort | \
    xargs -I {} bash -c '
      echo "=== Running: {} ===" && \
      ./bin/phel run {} && \
      echo "" || \
      (echo "❌ ERROR in {}" && exit 1)
    '
```
