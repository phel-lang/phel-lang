# Profile Module

Instrumentation profiler for `phel profile` command. Reports per-fn call counts, self/total/avg/max timings, and compile-time phase costs.

## Public API (Facade)

- `startSession(): ProfilerSession`
- `renderTable(ProfileReport, int $top, SortOrder $sort, bool $includeCompilePhases): string`
- `renderJson(ProfileReport): string`

## Hook Installation

`Phel\Lang\Registry` carries optional `static ?ProfilerHookInterface $profilerHook`. When set, `Registry::addDefinition` wraps each `AbstractFn` in `ProfilingFn` proxy before storing. `GlobalVarEmitter` routes all global-fn calls through `\Phel::getDefinition(...)`, so no emitter changes needed. Off-state cost: one null-check per `addDefinition`; zero call-site overhead when disabled.

## Dependencies

Run (`runFile`, `runNamespace`, `autoDetectEntryPoint`, error writers), Shared (`Munge` namespace canonicalization), Lang (`AbstractFn`, `ProfilerHookInterface`, `Registry`).

## Key Constraints

- Hook wraps fns at definition-time only; non-`AbstractFn` values pass through unchanged
- `ProfilingFn` extends `AbstractFn` so downstream `instanceof` checks succeed; copies inner meta so `getMeta()`/`withMeta()` work via `MetaTrait`
- Self time: `ProfilerSession` maintains per-call stack, subtracts child inclusive time from parent
- Self-recursive calls bypass proxy (`$this(...)` vs registry lookup); entry from outside counted, recursion depth not
- `ProfileCommand` installs hook before run, clears in `finally` block
