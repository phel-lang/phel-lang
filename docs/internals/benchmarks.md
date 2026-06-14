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

## Precompiled stdlib in the PHAR

A cold run still has to compile the bundled `phel.*` stdlib (most of the cost is `phel.core`) before it can run your code. To avoid that, the PHAR ships a read-only, content-addressed bundle of the precompiled stdlib under `cache/precompiled/`, generated at build time by `build/build-phar.php` (which invokes the internal `phel _precompile-bundled <dir>` command).

At runtime the compiled-code cache (`CompiledCodeCache`) consults this bundle whenever its writable cache misses, keyed by the source content hash. So a cold `phel run` in any project reuses the precompiled stdlib instead of recompiling it, approaching warm-cache speed on the very first run. The writable project cache still wins when present, and the bundle is ignored entirely for plain source or composer checkouts (where it is not shipped), leaving their behaviour unchanged.
