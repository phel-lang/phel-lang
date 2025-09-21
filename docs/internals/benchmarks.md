# Benchmarking Phel

This project keeps [PHPBench](https://phpbench.readthedocs.io/) in `require-dev` so we can measure the runtime cost of important developer workflows and core data structures. Running the benchmark suite regularly makes it easier to spot performance regressions before they reach a release.

The suite currently focuses on three high-impact areas:

- **CLI commands** &ndash; `phel run` and `phel test` are exercised end-to-end to make sure command line tooling stays responsive.
- **Persistent collections** &ndash; vectors and hash maps are checked for hot operations such as `append`, `update`, and `put`.
- **Core bootstrap** &ndash; loading and executing the bundled `phel\core` namespace ensures the compiler pipeline keeps its startup time predictable.

## Running the suite

You can execute the complete suite with:

```bash
composer phpbench
```

To keep historical measurements for comparison, record a baseline run and then compare the current branch against it:

```bash
# Create or update the baseline numbers (stored under the `baseline` tag)
composer phpbench-base

# Compare the current state with the recorded baseline
composer phpbench-ref
```

The assertion configured in [`phpbench.json`](../../phpbench.json) will fail the comparison when the measured mode deviates by more than some % from the baseline.

For quick local checks you can override the number of iterations and revolutions:

```bash
composer phpbench -- --iterations=2 --revs=10
```

Refer to the [official documentation](https://phpbench.readthedocs.io/) for advanced reporting and configuration options.
