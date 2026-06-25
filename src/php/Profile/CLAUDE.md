# Profile Module

Instrumentation profiler for `phel profile`. Reports per-fn call counts, self/total/avg/max timings, and compile-time phase costs.

## Public API (Facade)

`ProfileFacade` extends `AbstractFacade` (no interface).

| Method | Notes |
|--------|-------|
| `startSession(): ProfilerSession` | Install the returned session as `Registry::$profilerHook` for the run, then call its `stop()` to collect the `ProfileReport`. |
| `renderTable(ProfileReport $report, int $top, SortOrder $sort, bool $includeCompilePhases): string` | ASCII table; `$top` truncates rows. |
| `renderJson(ProfileReport $report): string` | Serialize report to JSON. |

## Dependencies

| Via | What |
|-----|------|
| `ProfileProvider::FACADE_RUN` → `RunFacade` | `runFile`, `runNamespace`, `autoDetectEntryPoint`, `writeLocatedException`, `writeStackTrace` |
| `Phel\Shared\Munge` (direct) | `canonicalNs` for namespace targets |
| `Phel\Lang` (types) | `AbstractFn`, `ProfilerHookInterface`, `Registry` |

## How the hook works

- `Registry` carries `static ?ProfilerHookInterface $profilerHook` (default null). When set, `Registry::addDefinition` calls `$profilerHook->wrapFn($value)` on each `AbstractFn` before storing; non-`AbstractFn` values pass through.
- `ProfilerSession implements ProfilerHookInterface`; its `wrapFn` returns a `ProfilingFn` proxy (idempotent — an already-wrapped fn is returned as-is, never double-wrapped).
- `GlobalVarEmitter` already routes global-fn calls through `\Phel::getDefinition(...)`, so the wrapped proxy is hit with no emitter changes. Off-state cost: one null-check per `addDefinition`; zero call-site overhead when disabled.

## Structure

| Path | Role |
|------|------|
| `Domain/ProfilerSession.php` | Hook impl; per-call stack, timing, `stop()` → `ProfileReport` |
| `Domain/ProfilingFn.php` | Proxy `extends AbstractFn`; times `__invoke` |
| `Domain/ProfileReport.php` | Immutable stats snapshot |
| `Domain/Formatter/{Table,Json}Formatter.php` | Render report |
| `Domain/{SortOrder,ReportFormat}.php` | Backed enums for `--sort` / `--format` |
| `Infrastructure/Command/ProfileCommand.php` | `phel profile` CLI |

## Key Constraints

- `ProfilingFn extends AbstractFn` so downstream `instanceof` checks succeed; constructor copies inner meta (`withMeta($inner->getMeta())`) so `getMeta()`/`withMeta()` work via `MetaTrait`.
- Fn name comes from the inner fn's `BOUND_TO` class constant (via reflection); falls back to `<anonymous>`.
- Self-recursive calls are emitted as `$this(...)`, not a registry lookup, so they bypass the proxy and stay untimed — outer entry counted, recursion depth not. This is a compiler emit detail (commit bee78ffe), not a constraint of `ProfilingFn`.
- Self time: `ProfilerSession` maintains a per-call stack and subtracts each child's inclusive time from its parent's self-time. Unmatched `exit()` on an empty stack is silently ignored.
- The hook is global and not reentrant — never run a profiled script inside another profiling context.
- `ProfileCommand` installs the hook before the run and clears it (`Registry::$profilerHook = null`) in a `finally` block.
