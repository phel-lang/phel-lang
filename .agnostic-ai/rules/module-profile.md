---
name: module-profile
description: 'Load when editing src/php/Profile: the instrumentation profiler behind `phel profile`.'
scope: src/php/Profile
---

# Profile Module

Instrumentation profiler for `phel profile`. Reports per-fn call counts, self/total/avg/max timings and compile-time phase costs.

## Internal tooling API (Facade)

`ProfileFacade` extends `AbstractFacade` (no interface).

| Method | Notes |
|--------|-------|
| `startSession(): ProfilerSession` | Install the session through `Registry::setProfilerHook()` for the run, then call its `stop()` to collect the `ProfileReport`. |
| `renderTable(ProfileReport $report, int $top, SortOrder $sort, bool $includeCompilePhases): string` | ASCII table; `$top` truncates rows. |
| `renderJson(ProfileReport $report): string` | Report as JSON. |

Internal CLI plumbing, outside the public PHP semver surface (ADR 0021).

## Dependencies

| Via | What |
|-----|------|
| `RunFacadeInterface::class` → `RunFacade` | `runFile`, `runNamespace`, `autoDetectEntryPoint`, `writeLocatedException`, `writeStackTrace` |
| `Phel\Shared\Munge` (direct) | `canonicalNs` for namespace targets |
| `Phel\Lang` (types) | `AbstractFn`, `ProfilerHookInterface`, `Registry` |
| `Phel\Phel` (composition root, direct) | `ProfileCommand` calls `setupRuntimeArgs()` before the profiled entry point, as `RunCommand` does |

`Phel\Phel` is the only import reaching *out* of the module graph into the composition root. It is not a cycle (the root never imports Profile), so `ModuleDependencyCycleTest` cannot see it; `CompositionRootBoundaryTest` pins it, and pins `setupRuntimeArgs()` as the single root method used.

## How the hook works

- `Registry` carries a private profiler hook (default null) via `getProfilerHook()` / `setProfilerHook()`. When set, `Registry::addDefinition` calls `$profilerHook->wrapFn($value)` on each `AbstractFn` before storing; other values pass through.
- `ProfilerSession implements ProfilerHookInterface`; `wrapFn` returns a `ProfilingFn` proxy (idempotent: an already-wrapped fn is returned as-is).
- `GlobalVarEmitter` already routes global-fn calls through `\Phel::getDefinition(...)`, so the proxy is hit with no emitter changes. Off-state cost: one null-check per `addDefinition`; no call-site overhead.

## Structure

| Path | Role |
|------|------|
| `Domain/ProfilerSession.php` | Hook impl; call stacks, timing, `stop()` → `ProfileReport` |
| `Domain/CallStack.php` | Open frames of one fiber, or of the main program |
| `Domain/ProfilingFn.php` | Proxy `extends AbstractFn`; times `__invoke` |
| `Domain/ProfileReport.php` | Immutable stats snapshot |
| `Domain/Formatter/{Table,Json}Formatter.php` | Render report |
| `Domain/{SortOrder,ReportFormat}.php` | Backed enums for `--sort` / `--format` |
| `Infrastructure/Command/ProfileCommand.php` | `phel profile` CLI |

## Key Constraints

- `ProfilingFn extends AbstractFn` so downstream `instanceof` checks pass; `ProfilerSession::wrapFn()` copies the inner fn's metadata onto the proxy with `withMeta()`.
- Fn name comes from the inner fn's `BOUND_TO` class constant (reflection); falls back to `<anonymous>`.
- Self-recursive calls are emitted as `$this(...)`, not a registry lookup, so they bypass the proxy and stay untimed: outer entry counted, recursion depth not. This is a compiler emit detail (commit bee78ffe), not a `ProfilingFn` constraint.
- Self time: `ProfilerSession` keeps one call stack for the main program and one per fiber (a `WeakMap`), so interleaved fibers never pop each other's frames, and subtracts each child's inclusive time from its parent's self-time. Unmatched `exit()` on an empty stack is ignored.
- The hook is global and not reentrant; never run a profiled script inside another profiling context.
- `ProfileCommand` installs the hook before the run and clears it (`Registry::setProfilerHook(null)`) in a `finally` block.
