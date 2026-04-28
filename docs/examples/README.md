# Phel single-file showcase

Standalone scripts from primitive literals through concurrency to a full CLI. Run any file with:

```bash
./bin/phel run docs/examples/<file>.phel
```

## Example overview

| # | File | Topic |
|---|------|-------|
| 01 | `01_basic-types.phel` | Primitive values, keywords, collection literals |
| 02 | `02_arithmetic.phel` | Numeric operations and helper functions |
| 03 | `03_control-flow.phel` | `cond` and `case` for branching |
| 04 | `04_functions-recursion.phel` | Reusable helpers, `loop`/`recur`, recursion |
| 05 | `05_data-structures.phel` | Vectors, maps, `conj`, `assoc`, nested updates, transients |
| 06 | `06_data-pipeline.phel` | Threading macros, `filter`/`map`/`reduce`, `group-by` |
| 07 | `07_macro-playground.phel` | A small DSL built with `defmacro` |
| 08 | `08_interfaces.phel` | `definterface` + `defstruct` polymorphism |
| 09 | `09_php-integration.phel` | PHP interop: `DateTimeImmutable`, `DateInterval`, JSON |
| 10 | `10_html-rendering.phel` | HTML templating with `phel\html` |
| 11 | `11_async-concurrency.phel` | AMPHP + fiber concurrency (`async`, `await`, `promise`) |
| 12 | `12_cli.phel` | A todo-list CLI on `phel\cli` with subcommands and tables |
| — | `transducers.phel` | Composable transformations: `into`, `transduce`, `sequence` |

Copy any file into your project and tweak it.

## Run all

```bash
find docs/examples -name "*.phel" -type f | \
    sort | \
    xargs -I {} bash -c '
      echo "=== Running: {} ===" && \
      ./bin/phel run {} && \
      echo "" || \
      (echo "ERROR in {}" && exit 1)
    '
```

CI's `examples` job runs the same command on every push.
