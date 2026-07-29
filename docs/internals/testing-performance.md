# Testing Performance &amp; DX

How the suites are wired, where wall-clock goes, and the fast paths for local
work. Numbers are from a 10-core dev machine (June 2026), indicative rather than
a benchmark. Re-measure before acting on them.

## The three suites

| Suite | Command | Count | Wall-clock | Parallel |
|-------|---------|-------|-----------|----------|
| PHPUnit `unit` | `composer test-unit` | 3850 tests | ~4 s | no (not needed) |
| PHPUnit `integration` | `composer test-integration` | 984 tests | ~37 s | yes (paratest) |
| Phel core | `composer test-core` | ~6100 tests | ~6 s warm serial | opt-in (`--parallel`) |

The full gate is `composer test` → `test-all`: `test-quality` (cs-fixer, psalm,
phpstan, rector), `test-compiler` (unit + integration), `test-core`. It reuses the
psalm/phpstan result caches; `composer test-all:fresh` clears them first.

`test-core` wall-clock swings with the compiled-PHP cache: a cold first run after
a checkout pays the full compile. Numbers above are warm.

## Where the time goes

- **`unit` is already fast** (~1 ms/test). Leave it alone.
- **`integration` was the bottleneck.** 421 `.test` fixtures expand to 984 PHPUnit
  invocations via data providers, each driving the full lexer → parser → analyzer
  → emitter pipeline (~92 ms per invocation). Now parallelized (see below);
  `composer test-integration:serial` keeps the single-process path for debugging.
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
composer test-core:parallel             # core lib across workers

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

## Open: convert passive mocks to stubs

All 98 PHPUnit notices in the `unit` suite are one category: *"No expectations
were configured for the mock object … use a test stub instead."* They come from
`createMock()` used as a passive stub. The fix is mechanical but wide,
`createMock(X::class)` → `createStub(X::class)` at the no-expectation sites across
~17 files (`CompilerFacadeInterface`, `NamespaceExtractorInterface`,
`BuildFacadeInterface`, `CommandFacadeInterface`, `PhelFnLoaderInterface`,
`PrinterInterface`). Each site needs checking for an absent `->expects()` first,
so it warrants its own PR.
