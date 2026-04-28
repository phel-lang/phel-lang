# Benchmarking Phel

[PHPBench](https://phpbench.readthedocs.io/) is in `require-dev` to measure runtime cost of developer workflows and core data structures. Run the suite regularly to spot regressions before release.

The suite covers three areas:

- **CLI commands**: `phel run` and `phel test` exercised end-to-end.
- **Persistent collections**: vectors and hash maps checked for hot operations like `append`, `update`, and `put`.
- **Core bootstrap**: loading and executing the bundled `phel\core` namespace keeps compiler startup time predictable.

## Running the suite

Execute the full suite with:

```bash
composer phpbench
```

Record a baseline and compare the current branch against it:

```bash
# Create or update the baseline numbers (stored under the `baseline` tag)
composer phpbench-base

# Compare the current state with the recorded baseline
composer phpbench-ref
```

The assertion in [`phpbench.json`](../../phpbench.json) fails the comparison when the measured mode deviates beyond the configured threshold.

For quick local checks, override iterations and revolutions:

```bash
composer phpbench -- --iterations=2 --revs=10
```

See the [PHPBench docs](https://phpbench.readthedocs.io/) for advanced reporting and configuration.
