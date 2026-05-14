# Parallel Test Runner

`phel test --parallel=<N|auto|max>` fans out test namespaces across a
pool of subprocess workers, mirroring Clojure's
[eftest](https://github.com/eftest/eftest) `:multithread? :namespaces`
mode: opt-in, default workers ≈ CPU count, deterministic output,
no impact on existing serial runs.

## When to use it

- A suite with non-trivial per-namespace work (DB-spinup, AST
  benchmarks, heavy property tests).
- CI where the wall clock dominates the queue cost of spawning more
  workers.

Skip it for very small suites or when a per-namespace fixture spins
up a shared resource (DB, port, fixture file) that doesn't tolerate
N concurrent loaders.

## Usage

```bash
phel test --parallel=4         # exactly 4 workers
phel test --parallel=auto      # CPU autodetect, capped at 8
phel test --parallel=max       # every core the kernel reports, uncapped
phel test --parallel=1         # collapses back to the serial path
```

You can also set the worker count via env var:

```bash
PHEL_TEST_WORKERS=12 phel test --parallel=auto
```

The env var overrides the cap on both `auto` and `max`, so power
users can dial in their own ceiling without touching the CLI.

## Output

```text
Running 33 namespace(s) across 4 parallel worker(s)...
 33/33 [============================] 100%   1 s — phel-test.are

Passed:  55
Failed:  0
Error:   0
Total:   55

Ran 33 namespace(s) across 4 worker(s) in 1.49s.
```

- A live `Symfony\ProgressBar` shows progress while workers run.
- Passing namespaces just bump the bar.
- Failing or erroring namespaces print their full captured block under
  a `--- ns ---` header, so failure context is preserved.
- A single aggregate `Passed / Failed / Error / [Skipped] / Total`
  block is printed at the end, computed from per-worker counts.

Output is **deterministic in input order**: workers complete in
arbitrary order, but the parent buffers results per slot and only
flushes a slot when every preceding namespace has finished. The same
input always produces the same on-screen sequence.

## Auto-disable rules

`--parallel` is silently downgraded to serial when:

- `--reporter=tap` is selected — TAP needs a monotonic test counter.
- `--list` is selected — discovery only, nothing to fan out.
- A profiler hook is installed — counts only accrue in the parent.

Run with `-v` to see why parallelism was skipped:

```
Ignoring --parallel: TAP reporter requires a monotonic test counter.
```

## CPU detection

`--parallel=auto` and `--parallel=max` share a small fallback chain:

1. `PHEL_TEST_WORKERS` env var (if set to a positive integer)
2. `nproc` on PATH
3. `sysctl -n hw.ncpu` (macOS/BSD)
4. `/proc/cpuinfo` line count (Linux without `nproc`)
5. Hardcoded fallback of `4`

`auto` clamps the result to a cap of 8 so a default run stays kind
to laptops and shared CI runners. `max` skips the cap.

## Architecture (one-paragraph cheat sheet)

The parent process runs `phel test` as usual, but instead of
generating one big Phel expression for every namespace, it spawns N
subprocesses (`phel _test-worker`, hidden), each holding open over
stdin / stdout. The parent sends one work frame per namespace
(length-prefixed JSON: `{index, ns, file, options}`); each worker
loads the namespace, runs `(phel\test/run-tests ...)` in a captured
output buffer, and writes back a result frame (`{index, ns, ok,
output, failed-tests, counts, error}`). The parent keeps results
indexed by their input position and flushes them to the terminal in
order; the live progress bar advances whenever a slot resolves.

No `pcntl_fork`, no shared memory, no threads — just `proc_open`
and `stream_select`. Works on Linux and macOS. Windows is untested.

## Caveats

- **Each worker pays one-time bootstrap cost** to `(phel\test/run-tests ...)` so very small suites may run slower in parallel mode than serial.
- **Stateful per-namespace fixtures** (DB seeding, port binding, shared file) need to be made worker-safe or you have to keep `--parallel=1` for that suite.
- **TAP / profiler combos auto-disable**; junit-xml works fine.
- **Cross-worker `--fail-fast` not yet wired** — fail-fast within a worker still works, but the parent does not cancel sibling workers on first failure.

## See also

- `phel test --help` — full flag reference
- [Quickstart: Tests](quickstart.md#tests)
- `src/php/Run/CLAUDE.md` — module-level architecture notes
