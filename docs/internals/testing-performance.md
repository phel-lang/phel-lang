# Testing Performance &amp; DX

How the test suites are wired, where the wall-clock goes, and the fast paths
for local work. Numbers below are from a 10-core dev machine (June 2026) and
are indicative, not a benchmark — re-measure before acting on them.

## The three suites

| Suite | Command | Count | Wall-clock | Parallel today |
|-------|---------|-------|-----------|----------------|
| PHPUnit `unit` | `composer test-unit` | 3850 tests | ~4 s | no (not needed) |
| PHPUnit `integration` | `composer test-integration` | 984 tests | ~91 s | no |
| Phel core | `composer test-core` | ~6100 tests | ~6 s warm serial | opt-in (`--parallel`) |

The full gate is `composer test` → `test-all`: `test-quality` (cs-fixer, psalm,
phpstan, rector), `test-compiler` (unit + integration), and `test-core`. It
reuses the psalm/phpstan result caches; `composer test-all:fresh` clears them
first for a cold run.

> `test-core` wall-clock swings with the compiled-PHP cache state: a cold first
> run after a checkout pays the full compile, warm runs are several times
> faster. Numbers below are warm.

## Where the time goes

- **`unit` is already fast** (~1 ms/test). Leave it alone.
- **`integration` is the bottleneck.** 421 `.test` fixtures (plus the other
  integration cases) expand to 984 PHPUnit invocations via data providers, each
  driving the full lexer → parser → analyzer → emitter pipeline, single-process
  (~92 ms per invocation).
- **`test-core --parallel` now reuses per-worker state.** Workers are
  long-lived and used to re-evaluate their whole dependency closure (mostly the
  shared `phel.*` stdlib) on every namespace frame — re-executing already-loaded
  code dominated each frame, so `--parallel=2` was *slower* than serial and
  4-vs-8 workers plateaued. Each dependency is now evaluated once per worker
  (like the serial runner). Warm local figures: serial ~5.9 s, `--parallel=auto`
  ~4.4 s before → ~3.9 s after. The relative win is modest on this small core
  suite (real per-frame work is only ~20 ms) but grows with the number of
  namespaces in a project, since the removed re-eval is fixed-cost per frame.

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

## Remaining improvements (not yet adopted)

### 1. Parallelize `integration` with paratest — ~4× win, but gated on isolation

A spike with `brianium/paratest -p8` ran the suite in **~22 s vs ~91 s (4.1×)**.
The bulk parallelizes cleanly, but 8 command-level E2E classes share
filesystem / process / global-compiler state:

- `Api/AnalyzeCommandTest`
- `Build/Command/BuildCommandTest`, `Build/Command/BuildCommandLoadE2ETest`
- `Lint/LintCommandTest`
- `Run/Command/Compile/CompileCommandTest`
- `Run/Command/Eval/EvalCommandTest`
- `Run/Command/Repl/ReplLazyBundledNamespaceTest`
- `Run/Command/Test/TestCommandParallel/ParallelTestRunnerTest`

These can't simply be split into a serial group: tagging them `@group serial`
and running just that group in one process **still fails** —
`BuildCommandLoadE2ETest` has a latent inter-class isolation bug that the full
random-order suite currently masks. Adopting paratest needs that real isolation
work first (per-test temp-dir isolation; `RealFilesystem::$files` being a
process-global static is the root of much of the coupling), so it is left as a
dedicated follow-up.

### 2. Drop CLI-opcache cost in `--parallel` workers — adopted (#2628)

With per-frame re-eval removed (see "Where the time goes"), the next parallel
ceiling was that CLI opcache is off, so each worker re-parsed every compiled
`.php` it requires. Workers are now spawned with a shared on-disk opcache
file-cache (`-d opcache.enable_cli=1 -d opcache.file_cache=<temp-dir>/opcache-workers`)
so worker N reuses what worker 1 compiled. `RunFactory` enables it only when the
Zend OPcache extension is loaded (a graceful no-op otherwise) and pre-creates the
cache dir, which PHP requires to exist at startup. The serial path stays
opcache-off by design.

### 3. Convert passive mocks to stubs (the 98 `unit` notices)

All 98 PHPUnit notices in the `unit` suite are one category: *"No expectations
were configured for the mock object … use a test stub instead."* They come from
`createMock()` used as a passive stub (no `->expects()`). The fix is mechanical
but wide — `createMock(X::class)` → `createStub(X::class)` at the no-expectation
sites across ~17 files (interfaces: `CompilerFacadeInterface`,
`NamespaceExtractorInterface`, `BuildFacadeInterface`, `CommandFacadeInterface`,
`PhelFnLoaderInterface`, `PrinterInterface`). Each site must be checked for an
absent `->expects()` before converting, so it warrants its own focused PR.
