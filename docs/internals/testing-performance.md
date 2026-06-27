# Testing Performance &amp; DX

How the test suites are wired, where the wall-clock goes, and the fast paths
for local work. Numbers below are from a 10-core dev machine (June 2026) and
are indicative, not a benchmark — re-measure before acting on them.

## The three suites

| Suite | Command | Count | Wall-clock | Parallel today |
|-------|---------|-------|-----------|----------------|
| PHPUnit `unit` | `composer test-unit` | 3850 tests | ~4 s | no (not needed) |
| PHPUnit `integration` | `composer test-integration` | 984 tests | ~91 s | no |
| Phel core | `composer test-core` | ~6100 assertions | ~93 s serial | opt-in (`--parallel`) |

The full gate is `composer test` → `test-all`: clears static-analysis caches,
then runs `test-quality` (cs-fixer, psalm, phpstan, rector), `test-compiler`
(unit + integration), and `test-core`.

## Where the time goes

- **`unit` is already fast** (~1 ms/test). Leave it alone.
- **`integration` is the bottleneck.** 421 `.test` fixtures (plus the other
  integration cases) expand to 984 PHPUnit invocations via data providers, each
  driving the full lexer → parser → analyzer → emitter pipeline, single-process
  (~92 ms per invocation).
- **`test-core` barely speeds up under `--parallel`.** Measured 93 s serial vs
  79 s at `--parallel=auto` (8 workers) — only ~1.2×. Each subprocess worker
  re-boots `phel.core` from scratch, so worker startup dominates over the
  per-namespace work being distributed.

## Fast local workflows

You rarely need the whole gate while iterating:

```bash
composer test-unit                      # ~4 s — pure PHP logic
composer test-integration               # compiler fixtures only
composer test-core:parallel             # core lib across workers

./bin/phel test --filter=<regex>        # one test by name
./bin/phel test --ns="phel.http.*"      # one namespace glob
./bin/phel test --last-failed           # re-run only last run's failures
./bin/phel test --watch                 # re-run on .phel change
./bin/phel test --slowest=10            # surface the slow tests
```

`phel test` already supports `--filter`, `--ns`, `--include`/`--exclude` tags,
`--last-failed`, `--watch`, `--repeat`, `--seed`/`--random-order`, `--slowest`,
multiple reporters, and `--coverage` — see `phel test --help`.

## Proposed improvements (not yet adopted)

### 1. Parallelize `integration` with paratest — ~4× win, but gated

A spike with `brianium/paratest -p8` ran the suite in **~22 s vs ~91 s (4.1×)**.
The bulk parallelizes cleanly, but 8 command-level E2E classes share
filesystem / process / global-compiler state and fail under parallel workers:

- `Api/AnalyzeCommandTest`
- `Build/Command/BuildCommandTest`, `Build/Command/BuildCommandLoadE2ETest`
- `Lint/LintCommandTest`
- `Run/Command/Compile/CompileCommandTest`
- `Run/Command/Eval/EvalCommandTest`
- `Run/Command/Repl/ReplLazyBundledNamespaceTest`
- `Run/Command/Test/TestCommandParallel/ParallelTestRunnerTest`

Adopting paratest needs these isolated first — either a separate serial group
(run them outside the parallel pass) or per-test temp-dir isolation so workers
don't collide on shared paths. `RealFilesystem::$files` being a process-global
static is the root of much of this coupling.

### 2. Split the cache-clear out of the default gate

`test-all` runs `static-clear-cache` (psalm + phpstan) on every invocation, so
local full-gate runs always re-analyze cold. Psalm/PHPStan result caches are
keyed on file content + config, so reuse is safe; clearing is only needed when
you suspect a stale cache. Consider keeping the clear as a separate
`test-all:fresh` (or CI-only) and letting the default reuse caches.

### 3. Quiet the 98 PHPUnit notices in `unit`

The unit suite reports 98 notices. They are noise that hides real signal in the
summary line; worth triaging and resolving (or asserting against) so a clean
run reads clean.

### 4. Speed up `--parallel` worker startup for `test-core`

The weak parallel speedup points at per-worker `phel.core` boot cost. Warming
or sharing a compiled-core snapshot across workers would make `--parallel`
worth defaulting on in CI and `test-core`.
