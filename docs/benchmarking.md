# Benchmarking

Phel has two benchmark layers, and they answer different questions.

| Layer | Written in | Runs with | Answers |
|---|---|---|---|
| `defbench` / `phel bench` | Phel | `phel bench` | "did my Phel code get slower?" |
| `tests/php/Benchmark/` | PHP, PHPBench | `composer phpbench` | "did the compiler or the runtime get slower?" |

If you are writing an application or a library in Phel, you want the first one.
The second exists because a compiler regression can hide *below* a Phel call
site, where nothing written in Phel can see it, and because it is the layer the
repository's CI compares against the merge base.

## Writing a benchmark

```phel
(ns my-app.bench
  (:require phel.bench :refer [defbench]))

(defbench bench-sum
  {:revs 1000}
  (reduce + 0 (range 100)))
```

`defbench` defines an ordinary zero-argument function with `:bench` metadata, so
a benchmark stays callable by hand and shows up in `phel doc`. The body is
timed, never asserted.

An option map may follow the name:

| Key | Default | Meaning |
|---|---|---|
| `:revs` | 1000 | Calls per measured iteration; amortises the clock over the call |
| `:iterations` | 5 | Measured iterations; their spread becomes `rstdev` |
| `:warmup` | 1 | Unmeasured iterations first, so lazy loading is not part of the result |

Pick `:revs` so one iteration takes a few milliseconds. Too few and you measure
the timer; too many and the suite takes longer than anyone will wait for.

## Running

```sh
phel bench                          # every benchmark under the test dirs
phel bench src/my-app/bench.phel    # one file
phel bench --filter=sum             # only benchmarks whose name contains "sum"
phel bench --revs=10000             # override what the benchmarks ask for
```

Output is one row per benchmark:

```
benchmark                revs its     mean rstdev vs-baseline
my-app.bench/bench-sum   1000   5 31.709μs ±0.42%         new
```

`rstdev` is the number that says whether to believe the run. Above a few
percent, a comparison against a baseline is noise rather than a result: close
what else is running on the machine, or raise `:revs`.

A flag beats what the file asks for. `phel bench --revs=10000` really does run
10000 revs, even for a benchmark that declares `{:revs 10}`; an option a file
could silently override would be worse than no option at all.

## Comparing against a baseline

Absolute durations are not portable between machines, so the useful workflow is
two runs on the same machine:

```sh
git checkout main
phel bench --store=.phel/bench-baseline.json

git checkout my-branch
phel bench --ref=.phel/bench-baseline.json --tolerance=10
```

`--ref` fills in the `vs-baseline` column with a signed percentage. `--tolerance`
turns it into a verdict: any benchmark slower than its baseline by more than that
percentage makes `phel bench` exit non-zero, which is what a CI job needs.

A benchmark with no entry in the baseline reports `new` and can never fail the
run; adding one would otherwise break the first build that contains it.

## Benchmarking the compiler and runtime

`tests/php/Benchmark/` holds the PHPBench suite. Its `Phel/Core*Bench` classes
resolve a `phel.core` function out of the registry and call it as a PHP callable,
which measures the function while bypassing everything the compiler does to a
call site: arity selection, type-driven inlining, keyword hoisting.

```sh
composer phpbench           # run it
composer phpbench-base      # store the current state as `baseline`
composer phpbench-ref       # compare against that baseline
```

CI runs the suite twice on the same runner, once on the merge base and once on
the pull request, and fails the build at +25%. That gate can only protect
subjects that exist, so **a change that claims a performance win should add or
extend a subject**, on whichever layer it applies to.

Subjects there come in pairs, `bench_x` against `bench_x_raw`, where the raw one
performs the operation natively. The reviewable number is the ratio between the
pair rather than either duration: a ratio survives a change of machine, where an
absolute figure does not.
