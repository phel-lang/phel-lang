# Profile Module

`phel profile` command: instrumentation profiler for Phel scripts. Reports per-fn call counts, self/total/avg/max timings, and compile-time phase costs.

## Gacela Pattern

- **Facade**: `ProfileFacade` : `startSession()`, `renderTable()`, `renderJson()`
- **Factory**: `ProfileFactory` : creates `ProfilerSession`, formatters; exposes `RunFacade`
- **Config**: `ProfileConfig` : format/sort constants and defaults
- **Provider**: `ProfileProvider` : injects `RunFacade` (`FACADE_RUN`)

## Public API (Facade)

- `startSession(): ProfilerSession`
- `renderTable(ProfileReport, int $top, string $sort, bool $includeCompilePhases): string`
- `renderJson(ProfileReport): string`

## Hook Strategy

`Phel\Lang\Registry` carries an optional `static ?ProfilerHookInterface $profilerHook`. When set, `Registry::addDefinition` wraps every `AbstractFn` in a `ProfilingFn` proxy before storing. `GlobalVarEmitter` already routes all global-fn calls through `\Phel::getDefinition(...)`, so no emitter changes are needed.

Off-state cost: one null-check per `addDefinition`. Zero call-site overhead when off.

## Structure

```
Profile/
├── Domain/
│   ├── Formatter/
│   │   ├── JsonFormatter.php
│   │   └── TableFormatter.php
│   ├── ProfileReport.php       Immutable result: per-fn stats + per-source phase ms + wall-clock ms
│   ├── ProfilerSession.php     Implements ProfilerHookInterface; stack-based self/total accounting
│   ├── ProfilingFn.php         AbstractFn proxy; times each __invoke via session
│   ├── ReportFormat.php        Enum: Table, Json, Both (with emitsTable/emitsJson predicates)
│   └── SortOrder.php           Enum: SelfTime, TotalTime, Calls, Avg
├── Infrastructure/
│   └── Command/
│       └── ProfileCommand.php  Symfony Command; reuses RunFacade::runFile / runNamespace
└── Gacela files                ProfileFacade, ProfileFactory, ProfileConfig, ProfileProvider
```

## Dependencies

- **Run** (`RunFacade`) : `runFile`, `runNamespace`, `autoDetectEntryPoint`, `writeLocatedException`, `writeStackTrace`
- **Compiler** (`Munge`) : namespace canonicalisation
- **Lang** (`AbstractFn`, `ProfilerHookInterface`, `Registry`) : fn proxy and hook installation

## Key Constraints

- Hook wraps fns at definition time only; non-`AbstractFn` values pass through unchanged
- `ProfilingFn` extends `AbstractFn` so downstream `instanceof` checks still succeed
- `ProfilingFn::__construct` copies inner meta so `getMeta()` / `withMeta()` work via `MetaTrait`
- Self time is computed by maintaining a per-call stack and subtracting child inclusive time from parent
- Self-recursive calls bypass the proxy (`$this(...)` vs registry lookup); entry from outside is counted but recursive depth isn't
- Hook is installed by `ProfileCommand` before invoking the run, cleared in a `finally` block
