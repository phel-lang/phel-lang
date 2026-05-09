# Benchmarking Phel

[PHPBench](https://phpbench.readthedocs.io/) is in `require-dev` to measure runtime cost of developer workflows and core data structures. Run the suite to spot regressions before release.

Three areas:

- **CLI commands**: `phel run` and `phel test` end-to-end.
- **Persistent collections**: vectors and hash maps for hot ops like `append`, `update`, `put`.
- **Core bootstrap**: loading and executing the bundled `phel\core` namespace, to track compiler startup time.

## Running the suite

```bash
composer phpbench
```

Record a baseline and compare the current branch against it:

```bash
# Create or update baseline (stored under the `baseline` tag)
composer phpbench-base

# Compare current state with baseline
composer phpbench-ref
```

The assertion in [`phpbench.json`](../../phpbench.json) fails the comparison when the measured mode deviates beyond the configured threshold.

Override iterations and revolutions for quick local checks:

```bash
composer phpbench -- --iterations=2 --revs=10
```

JIT specialization wins on `:tag`-annotated fns can be measured by `TypedSignatureBench`:

```bash
composer bench-jit-baseline   # opcache off, no JIT
composer bench-jit-tracing    # opcache on, tracing JIT
```

Compare the two runs to see what typed signatures unlock at runtime.

See the [PHPBench docs](https://phpbench.readthedocs.io/) for advanced reporting.
