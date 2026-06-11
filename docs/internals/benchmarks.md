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
