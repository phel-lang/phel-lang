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

## Snippets

`snippets/` holds tiny single-function demos, one symbol per file (Janet-style):

| Topic | File |
|-------|------|
| `+`, `-`, `apply` | `snippets/apply.phel` |
| `map` | `snippets/map.phel` |
| `filter`, `remove` | `snippets/filter.phel` |
| `reduce` | `snippets/reduce.phel` |
| `conj` | `snippets/conj.phel` |
| `assoc`, `assoc-in` | `snippets/assoc.phel` |
| `get`, `get-in` | `snippets/get.phel` |
| `into` | `snippets/into.phel` |
| `merge`, `merge-with` | `snippets/merge.phel` |
| `range` | `snippets/range.phel` |
| `if`, `cond` | `snippets/if-cond.phel` |
| `let`, destructuring | `snippets/let.phel`, `snippets/destructure.phel` |
| `loop`/`recur` | `snippets/loop-recur.phel` |
| `->`, `->>` | `snippets/threading.phel` |
| `defn`, multi-arity | `snippets/defn.phel` |
| `lazy-seq`, infinite seqs | `snippets/lazy.phel` |
| `atom`, `swap!`, `reset!` | `snippets/atom.phel` |
| `phel\string` ops | `snippets/string.phel` |

Each snippet is runnable: `./bin/phel run docs/examples/snippets/<name>.phel`. Use as REPL warm-ups or copy/paste recipes.

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
