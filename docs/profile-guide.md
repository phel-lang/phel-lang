# Profile Guide

`phel profile` instruments a script and reports per-fn call counts, self / total / avg / max timings, and compile-time phase costs. Use it to find hot fns and where compilation time goes.

## Quickstart

```sh
./vendor/bin/phel profile src/phel/main.phel
./vendor/bin/phel profile my.namespace
./vendor/bin/phel profile               # auto-detect: tries main.phel, then core.phel
```

## Sample output

```
Wall clock: 142.31 ms

Top 20 functions by self time:
  fn                          calls    self ms    total ms     avg ms     max ms
  user/parse-line             10000     38.420      52.110      0.005      0.114
  user/normalise-token        10000     11.604      11.604      0.001      0.043
  user/load-fixture               1     30.281     142.014    142.014    142.014
  ...

Compile phases (ms):
  src/phel/main.phel              lex 4.12   parse 1.88   read 1.02   analyze 8.41   emit 2.77
```

JSON form:

```sh
./vendor/bin/phel profile main.phel --format=json
./vendor/bin/phel profile main.phel --format=both --output=profile.json
```

`--output=<file>` always writes the JSON report. `--format` controls stdout (table / json / both).

## Options

| Flag | Default | Effect |
|---|---|---|
| `--top=<N>` | `20` | Rows in the table; non-positive falls back to default |
| `--format=table|json|both` | `table` | Stdout shape |
| `--output=<file>` | (none) | Write JSON to a file (independent of `--format`) |
| `--sort=self|total|calls|avg` | `self` | Table row ordering |
| `--no-compile-phases` | off | Drop the per-source phase block |

Argv after the path is forwarded to the script:

```sh
./vendor/bin/phel profile bench.phel -- --iters=1000
```

## How it works

An **instrumentation** profiler, not a sampler:

1. `Registry::$profilerHook` is set before the run. While set, `Registry::addDefinition` wraps every `AbstractFn` in a `ProfilingFn` proxy.
2. `GlobalVarEmitter` already routes every global-fn call through `\Phel::getDefinition(...)`, so each call hits the proxy's `__invoke`; no emitter or fixture changes.
3. Each `__invoke` notifies the session on entry/exit; the session keeps a per-call stack of `[name, enterNs, childInclusiveNs]`.
4. On exit, total = `now - enterNs`, self = `total - childInclusiveNs`; the parent frame's `childInclusiveNs` is bumped so its self time stays accurate.
5. Compile-phase timings come from the compiler hook (`recordPhase`), keyed by source file.
6. The hook is cleared in a `finally` block, so it never leaks across commands.

Off-state cost: one null-check per `Registry::addDefinition`. Zero overhead at call sites when not profiling.

## Caveats

- **Self-recursive calls** emit `$this(...)` instead of a registry lookup ([commit `bee78ffe`](https://github.com/phel-lang/phel-lang/commit/bee78ffe)). The outer entry is timed; recursive depth inside that call is not. Cross-fn recursion is fully profiled.
- **Macros** run at compile time. Their cost shows up under the `analyze` phase, not as fn rows.
- **Anonymous fns** (`fn`, `#(...)`) are not registered globally, so they do not appear in the per-fn table. Wrap them in a `defn` if you need to profile them.
- **Variadic spread** in `__invoke` adds a small per-call overhead. For micro-benchmarks below a microsecond, prefer `composer bench-jit-baseline` / `bench-jit-tracing`.

## See also

- [Performance Tips](performance.md): opcache, JIT, compiled-code cache
- [Internals: Benchmarks](internals/benchmarks.md): typed-vs-untyped JIT kernels
