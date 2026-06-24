# Benchmarking Phel

[PHPBench](https://phpbench.readthedocs.io/) (in `require-dev`) measures the runtime cost of developer workflows and core data structures. Run the suite to spot regressions before release.

It covers three areas:

- **CLI commands**: `phel run` and `phel test` end-to-end.
- **Persistent collections**: vectors and hash maps for hot ops like `append`, `update`, `put`.
- **Core bootstrap**: loading and executing the bundled `phel.core` namespace, to track compiler startup time.

## Running the suite

```bash
composer phpbench
```

Override iterations and revolutions for quick local checks:

```bash
composer phpbench -- --iterations=2 --revs=10
```

## Comparing against a baseline

```bash
composer phpbench-base   # record/update baseline (stored under the `baseline` tag)
composer phpbench-ref    # compare current state with baseline
```

The assertion in [`phpbench.json`](../../phpbench.json) fails the comparison when the measured mode deviates beyond the configured threshold.

## Measuring JIT gains

`TypedSignatureBench` measures the JIT specialization wins on `:tag`-annotated fns. Compare the two runs to see what typed signatures unlock at runtime:

```bash
composer bench-jit-baseline   # opcache off, no JIT
composer bench-jit-tracing    # opcache on, tracing JIT
```

See the [PHPBench docs](https://phpbench.readthedocs.io/) for advanced reporting.

## Measuring compiler phases

To quantify a compiler-phase change (lexer, parser, analyzer, emitter), build the project with `--timing`. It sums each phase's wall-clock across every compiled namespace. Pair it with `--no-cache` so the whole project recompiles and the numbers are comparable run to run:

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

Run it before and after a change and quote the before/after totals (and the affected phase's share) in the PR. This is compile-only — it never executes the built program, so the analyzer/emitter cost is isolated from runtime. `--timing` composes with `--report` (per-namespace sizes + build time). Without `--no-cache` only freshly compiled namespaces contribute; if everything is served from cache the report says so and tells you to re-run with `--no-cache`.

## Speeding up CLI runs with OPcache

Phel compiles each `.phel` file to PHP and caches the result under the Phel cache dir (`.phel/cache/compiled/` by default). A warm `phel run` then just `require`s the cached PHP instead of recompiling. The remaining gap versus native PHP comes from PHP re-parsing those cached files on every process, because **OPcache is disabled on the CLI by default** (`opcache.enable_cli=0`).

To let the compiled cache survive across CLI invocations, enable OPcache with its file cache:

```ini
; php.ini (or a -d flag on the CLI)
opcache.enable_cli=1
opcache.file_cache=/tmp/phel-opcache   ; any writable directory
opcache.file_cache_only=1              ; persist across short-lived CLI processes
```

`opcache.enable_cli` and `opcache.file_cache` are `PHP_INI_SYSTEM` settings, so they must be set in `php.ini` or via `php -d ...` before the process starts (they cannot be changed at runtime).

Run `phel doctor` to check the current configuration: its "Checking performance" section reports whether OPcache CLI caching is fully configured and prints tips otherwise.

## Ahead-of-time builds vs runtime compilation

For the fastest, closest-to-native execution, compile the project ahead of time and run the generated PHP directly, so the Phel compiler never runs:

```bash
composer install --no-dev --optimize-autoloader
./vendor/bin/phel build          # writes plain PHP to out/
php out/index.php                 # runs the built entry, zero runtime compilation
```

The built entry (`out/index.php`) requires the compiled namespace tree, which require-chains the precompiled `phel.core`. No lexer/parser/analyzer/emitter runs on the request path. Indicative CLI process-startup, best-of-5 on a trivial script (your numbers depend on how much stdlib the entry touches and on OPcache):

| Execution mode | Startup |
|---|---|
| `php out/index.php` (ahead-of-time build) | ~0.11s |
| `phel run` (warm cache) | ~0.20s |
| native PHP equivalent | ~0.05s |

The ahead-of-time artifact roughly halves the gap to native versus a warm `phel run`. See the example deployment guide (`resources/agents/examples/http-json-api/DEPLOYMENT.md`) for the full production flow (Docker, OPcache preload, php-fpm, worker mode).
