# Benchmarking Phel

This page is the PHPBench suite, which measures the compiler and the runtime.
To benchmark **Phel code**, use `defbench` and `phel bench`, documented in
[Benchmarking](../benchmarking.md).

[PHPBench](https://phpbench.readthedocs.io/) (in `require-dev`) measures five
areas: CLI commands (`phel run`, `phel test` end to end), persistent collections
(vector and hash-map hot ops), core bootstrap (loading and executing
`phel.core`, tracking compiler startup), the dispatch cost of the hot
`phel.core` functions themselves (`Phel/Core*Bench`), and the boundary a PHP
host calls Phel across (`Interop/ExportedCallBench`).

`Phel::bootstrap()` is not a subject anywhere, and cannot be: it memoizes, so
only the first call in a process pays, while the CI job runs with `--warmup=2`.
The load term that dominates a cold host is gated by `Run/ReplBootBench`
instead, which re-enters cleanly.

## What the core subjects are for

`Phel/CoreDispatchBench`, `CoreSeqBench`, `CoreArithmeticBench`,
`CoreConstructionBench` and `CoreMacroBench` call a core function through the
registry as a PHP callable. Every subject is paired with the raw operation the
function ultimately performs, `bench_x` against `bench_x_raw`, and the
reviewable number is the **ratio** between the pair rather than either
duration: a ratio survives a change of machine, where an absolute figure does
not. A ratio of 1.0 means the wrapper is free.

CI runs the whole suite twice on the same runner, once on the merge base and
once on the pull request, and fails at +25%. It can only guard subjects that
exist, so a change claiming a performance win should add or extend one.

```bash
composer phpbench
composer phpbench -- --iterations=2 --revs=10   # quick local check
```

## Comparing against a baseline

```bash
composer phpbench-base   # record/update the `baseline` tag
composer phpbench-ref    # compare current state against it
```

The assertion in [`phpbench.json`](../../phpbench.json) fails the comparison when
the measured mode deviates beyond the threshold.

## JIT gains

`TypedSignatureBench` measures JIT specialization on `:tag`-annotated fns:

```bash
composer bench-jit-baseline   # opcache off, no JIT
composer bench-jit-tracing    # opcache on, tracing JIT
```

## Compiler phases

`--timing` sums each phase's wall-clock across every compiled namespace. Pair it
with `--no-cache` so the whole project recompiles and runs are comparable:

```bash
./vendor/bin/phel build --no-cache --timing
```

```
Compile-phase timing
====================
  lex             0.32 ms    0.0%
  parse          86.68 ms    8.9%
  read           27.07 ms    2.8%
  analyze       395.82 ms   40.6%
  emit          464.81 ms   47.7%
  total         974.71 ms
  (33 namespaces compiled)
```

Quote before/after totals and the affected phase's share in the PR. Compile-only:
it never executes the built program, so analyzer and emitter cost are isolated
from runtime. Composes with `--report` (per-namespace sizes + build time). Without
`--no-cache` only freshly compiled namespaces contribute, and a fully cached run
says so.

## OPcache for CLI runs

Phel caches compiled PHP under `.phel/cache/compiled/`, so a warm `phel run` just
`require`s it. The remaining gap versus native PHP is PHP re-parsing those files
every process, because **OPcache is off on the CLI by default**
(`opcache.enable_cli=0`):

```ini
opcache.enable_cli=1
opcache.file_cache=/tmp/phel-opcache   ; any writable directory
opcache.file_cache_only=1              ; persist across short-lived CLI processes
```

Both are `PHP_INI_SYSTEM`, so they must be set in `php.ini` or via `php -d …`
before the process starts. `phel doctor` reports whether CLI caching is fully
configured.

## Ahead-of-time builds

Fastest path: compile ahead of time and run the generated PHP, so the compiler
never runs.

```bash
composer install --no-dev --optimize-autoloader
./vendor/bin/phel build          # writes plain PHP to out/
php out/index.php                # zero runtime compilation
```

`out/index.php` require-chains the compiled namespace tree, including the
precompiled `phel.core`. No pipeline phase runs on the request path. Indicative
process startup, best-of-5 on a trivial script:

| Execution mode | Startup |
|---|---|
| `php out/index.php` (ahead-of-time build) | ~0.11s |
| `phel run` (warm cache) | ~0.20s |
| native PHP equivalent | ~0.05s |

Roughly halves the gap to native versus a warm `phel run`. Full production flow
(Docker, OPcache preload, php-fpm, worker mode):
`resources/agents/examples/http-json-api/DEPLOYMENT.md`.
