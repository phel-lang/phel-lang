# Testing Performance &amp; DX

How the suites are wired, where wall-clock goes, and the fast paths for local
work. Numbers are from a 14-core dev machine (September 2026), indicative rather
than a benchmark. Re-measure before acting on them.

## The three suites

| Suite | Command | Count | Wall-clock | Parallel |
|-------|---------|-------|-----------|----------|
| PHPUnit `unit` | `composer test-unit` | 5220 tests | ~18 s | no |
| PHPUnit `integration` | `composer test-integration` | 1547 tests | ~115-130 s | yes (paratest) |
| Phel core | `composer test-core` | 10368 tests | ~9 s cold, ~1.5 s warm | yes (`--parallel=auto`) |

The full gate is `composer test` → `test-all`: `test-quality` (cs-fixer, psalm,
phpstan, rector), `test-compiler` (unit + integration), `test-core`. It reuses the
psalm/phpstan result caches; `composer test-all:fresh` clears them first.

`test-core` wall-clock swings with the compiled-PHP cache: a cold first run after
a checkout pays the full compile. That compile is most of the cost, and workers
split it: cold, serial takes ~49 s and `--parallel=auto` ~9 s. So `test-core`
runs in workers, then runs the three `^:timing-sensitive` tests alone, since they
assert concurrency by wall clock and would race the other workers.
`composer test-core:serial` keeps the one-process path.

## Where the time goes

- **`unit` is already fast** (~1 ms/test). Leave it alone.
- **`integration` is the bottleneck.** Its slow tests run `bin/phel` as a
  subprocess against a fixture project in a fresh temp dir, and each subprocess
  cold-compiles the bundled stdlib into that project's empty cache: 5 s alone,
  10-17 s under paratest's load. `PhelTest\Support\SharedStdlibCache` points
  such a subprocess at one build cache per paratest worker, so the stdlib
  compiles once per worker (`BenchCommandTest` 96 s to 24 s, `MutateCommandTest`
  91 s to 43 s). The project's own namespaces still compile cold,
  since each project lives at a fresh path. It is opt-in: the benchmark,
  mutation and `phel test` project suites use it. A test that runs
  `phel build`, reads the project's `.phel/cache` or asserts where the cache
  lives must not, and in-process tests never do. Making it global cost an
  intermittent `phel build` failure nobody could reproduce outside the suite.
  `composer test-integration:serial` keeps the single-process path for debugging.
- **Paratest schedules whole classes.** The slowest class sets the floor
  (now `BenchAbCommandTest`, ~65 s). `--functional` (per-method)
  and fewer processes were both measured slower (208 s and 187 s before the
  cache change), because the cost is CPU-bound compiling, not scheduling.
- **`test-core --parallel` reuses per-worker state.** Workers are long-lived and
  used to re-evaluate their whole dependency closure (mostly the shared `phel.*`
  stdlib) on every namespace frame, so `--parallel=2` was *slower* than serial and
  4-vs-8 workers plateaued. Each dependency is now evaluated once per worker.
  Warm: serial ~5.9 s, `--parallel=auto` ~4.4 s before → ~3.9 s after. Modest on
  this small core suite (~20 ms real work per frame) but grows with the number of
  namespaces, since the removed re-eval is fixed-cost per frame.

## Fast local workflows

```bash
composer test-unit                      # ~4 s, pure PHP logic
composer test-integration               # compiler fixtures only
composer test-core                      # core lib across workers
composer test-core:serial               # one process, for debugging

./bin/phel test --filter=<regex>        # one test by name
./bin/phel test --ns="phel.http.*"      # one namespace glob
./bin/phel test --last-failed           # re-run only last run's failures
./bin/phel test --watch                 # re-run on .phel change
./bin/phel test --slowest=10            # surface the slow tests
```

`phel test` also supports `--include`/`--exclude` tags, `--repeat`,
`--seed`/`--random-order`, multiple reporters and `--coverage`. See
`phel test --help`.

## Parallel isolation (adopted, #2630)

`composer test-integration` runs `brianium/paratest` with the `WrapperRunner`
(`-p auto`): ~37 s vs ~91 s serial on 10 cores, scaling with them.

The win was gated on cross-worker isolation, since paratest workers are separate
processes sharing a filesystem. Three couplings had to go:

- **Per-worker temp.** `tests/bootstrap.php` points each worker at its own
  `sys_get_temp_dir()` keyed by `TEST_TOKEN`, so every derived path (compiled-code
  cache, Gacela merged-config cache, parallel-runner opcache dir), in-process and
  in spawned `bin/phel` subprocesses, is worker-private. It reads
  `getenv('TMPDIR')` rather than `sys_get_temp_dir()`, which PHP caches on first
  call.
- **Build tests off the repo tree.** The `Build/Command` tests compiled into fixed
  in-repo `out*/` dirs and mutated in-repo fixtures. `NamespaceLoader` scans
  `getcwd()`, so a sibling worker mid-build surfaced this test's
  `out/phel/core.phel` as a duplicate of the real core and loaded the wrong file.
  `BuildCommandWorkspace` now gives each test an isolated project root under the
  worker-private temp.
- **`phel doc` temp file.** `PhelFunctionRuntimeLoader` wrote its generated
  `doc.phel` to a fixed path inside the package; concurrent `phel doc` runs
  clobbered it. It now uses a unique per-call temp dir, which also unblocks
  read-only installs.

## Worker opcache (adopted, #2628)

With per-frame re-eval removed, the next parallel ceiling was CLI opcache being
off, so each worker re-parsed every compiled `.php` it requires. Workers are now
spawned with a shared on-disk file cache
(`-d opcache.enable_cli=1 -d opcache.file_cache=<temp-dir>/opcache-workers`), so
worker N reuses what worker 1 compiled. `RunFactory` enables it only when the Zend
OPcache extension is loaded and pre-creates the dir, which PHP requires to exist
at startup. The serial path stays opcache-off by design.
