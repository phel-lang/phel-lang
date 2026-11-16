---
name: module-profile
description: 'Load when editing src/php/Profile: the instrumentation profiler behind `phel profile`.'
scope: src/php/Profile
---

# Profile Module

Instrumentation profiler for `phel profile`: per-fn call counts, self/total/avg/max timings and compile-phase costs.

`ProfileFacade` has no interface. Internal CLI plumbing, outside the public PHP semver surface (ADR 0021). `startSession()` installs the session through `Registry::setProfilerHook()`; its `stop()` returns the `ProfileReport`.

## Dependencies

| Via | What |
|-----|------|
| `RunFacadeInterface::class` | `runFile`, `runNamespace`, `autoDetectEntryPoint`, `writeLocatedException`, `writeStackTrace` |
| `Phel\Phel` (composition root) | `ProfileCommand` calls `setupRuntimeArgs()` before the profiled entry point, as `RunCommand` does |

The root import is not a cycle (the root never imports Profile), so `ModuleDependencyCycleTest` cannot see it. `CompositionRootBoundaryTest` pins it and pins `setupRuntimeArgs()` as the single root method used.

## How the hook works

- When `Registry`'s profiler hook is set, `Registry::addDefinition` calls `wrapFn($value)` on each `AbstractFn` before storing. Off-state cost: one null check per `addDefinition`, none at call sites.
- `ProfilerSession::wrapFn` returns a `ProfilingFn` proxy (idempotent) and copies the inner fn's metadata with `withMeta()`. `ProfilingFn extends AbstractFn` so `instanceof` checks pass.
- `GlobalVarEmitter` routes global-fn calls through `\Phel::getDefinition(...)`, so the proxy is hit with no emitter changes.

## Key Constraints

- Fn name comes from the inner fn's `BOUND_TO` constant, else `<anonymous>`.
- Self-recursive calls are emitted as `$this(...)`, bypassing the proxy: the outer entry is counted, recursion depth is not. That is a compiler emit detail, not a `ProfilingFn` limit.
- Self time: one `CallStack` for the main program and one per fiber (a `WeakMap`), so interleaved fibers never pop each other's frames. Each child's inclusive time is subtracted from its parent's self time. An unmatched exit on an empty stack is ignored.
- The hook is global and not reentrant; never profile inside another profiling context. `ProfileCommand` clears it (`setProfilerHook(null)`) in `finally`.
