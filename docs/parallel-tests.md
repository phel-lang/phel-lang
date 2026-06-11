# Parallel Test Runner

`phel test --parallel=<N|auto|max>` fans out test namespaces across a pool of subprocess workers, mirroring Clojure's [eftest](https://github.com/weavejester/eftest) `:multithread? :namespaces` mode: opt-in, default workers ≈ CPU count, deterministic output, no impact on serial runs.

## When to use it

| Use it | Skip it |
|---|---|
| Non-trivial per-namespace work (DB spin-up, AST benchmarks, heavy property tests) | Tiny suites |
| CI where wall clock dominates worker spawn cost | Per-namespace fixtures sharing a resource (DB, port, fixture file) that can't tolerate N concurrent loaders |

## Usage

```bash
phel test --parallel=4         # exactly 4 workers
phel test --parallel=auto      # CPU autodetect, capped at 8
phel test --parallel=max       # every core the kernel reports, uncapped
phel test --parallel=1         # collapses back to the serial path
```

Set the worker count via env var instead; it overrides the cap on both `auto` and `max`:

```bash
PHEL_TEST_WORKERS=12 phel test --parallel=auto
```

## Output

```text
Running 33 namespace(s) across 4 parallel worker(s)...
 33/33 [============================] 100%   1 s  phel-test.are

Passed:  55
Failed:  0
Error:   0
Total:   55

Ran 33 namespace(s) across 4 worker(s) in 1.49s.
```

A live `Symfony\ProgressBar` advances as namespaces finish; passing namespaces just bump the bar. Failing/erroring namespaces print their full captured block under a `--- ns ---` header. A single aggregate `Passed / Failed / Error / [Skipped] / Total` block prints at the end, computed from per-worker counts.

Output is **deterministic in input order**: workers complete in arbitrary order, but the parent buffers results per slot and flushes a slot only once every preceding namespace has finished. Same input, same on-screen sequence.

## Auto-disable rules

`--parallel` silently downgrades to serial when:

| Condition | Reason |
|---|---|
| `--reporter=tap` | TAP needs a monotonic test counter |
| `--list` | Discovery only, nothing to fan out |
| A profiler hook is installed | Counts only accrue in the parent |

Run with `-v` to see why:

```
Ignoring --parallel: TAP reporter requires a monotonic test counter.
```

## CPU detection

`auto` and `max` share a fallback chain:

1. `PHEL_TEST_WORKERS` (if a positive integer)
2. `nproc` on PATH
3. `sysctl -n hw.ncpu` (macOS/BSD)
4. `/proc/cpuinfo` line count (Linux without `nproc`)
5. Hardcoded `4`

`auto` clamps to a cap of 8 (kind to laptops and shared CI runners); `max` skips the cap.

## Architecture

The parent runs `phel test` as usual but, instead of one big Phel expression per namespace, spawns N hidden `phel _test-worker` subprocesses held open over stdin/stdout. It sends one length-prefixed JSON work frame per namespace (`{index, ns, file, options}`); each worker loads the namespace, runs `(phel.test/run-tests ...)` in a captured buffer, and writes back a result frame (`{index, ns, ok, output, failed-tests, counts, error}`). The parent keeps results indexed by input position and flushes them in order; the progress bar advances as slots resolve.

No `pcntl_fork`, no shared memory, no threads; just `proc_open` and `stream_select`. Works on Linux and macOS. Windows untested.

## Caveats

- **One-time bootstrap cost per worker**, so very small suites may run slower than serial.
- **Stateful per-namespace fixtures** (DB seeding, port binding, shared file) must be worker-safe, else keep `--parallel=1` for that suite.
- **TAP / profiler combos auto-disable**; junit-xml works fine.
- **Cross-worker `--fail-fast` not yet wired**: fail-fast within a worker works, but the parent does not cancel siblings on first failure.

## See also

- `phel test --help`: full flag reference
- [Quickstart: Tests](quickstart.md#tests)
- `src/php/Run/CLAUDE.md`: module architecture notes

---

📖 **Full guide:** [Testing on phel-lang.org](https://phel-lang.org/documentation/testing/)
