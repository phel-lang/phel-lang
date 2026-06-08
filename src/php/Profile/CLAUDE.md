# Profile Module

Instrumentation profiler for `phel profile` command. Reports per-fn call counts, self/total/avg/max timings, and compile-time phase costs.

## Gacela Pattern

- **Facade**: `ProfileFacade`
- **Factory**: `ProfileFactory` (creates `ProfilerSession`, formatters; provides `RunFacade`)
- **Config**: `ProfileConfig` (default top limit)
- **Provider**: `ProfileProvider` (injects `RunFacade` via `FACADE_RUN`)

## Public API (Facade)

- `startSession(): ProfilerSession`
- `renderTable(ProfileReport, int $top, SortOrder $sort, bool $includeCompilePhases): string`
- `renderJson(ProfileReport): string`

## Hook Installation

`Phel\Lang\Registry` carries optional `static ?ProfilerHookInterface $profilerHook`. When set, `Registry::addDefinition` wraps each `AbstractFn` in `ProfilingFn` proxy before storing. `GlobalVarEmitter` routes all global-fn calls through `\Phel::getDefinition(...)`, so no emitter changes needed. Off-state cost: one null-check per `addDefinition`; zero call-site overhead when disabled.

## Structure

```
Profile/
├── Domain/
│   ├── Formatter/
│   │   ├── JsonFormatter.php
│   │   └── TableFormatter.php
│   ├── ProfileReport.php       Immutable: per-fn stats, per-source phase ms, wall-clock ms
│   ├── ProfilerSession.php     Implements ProfilerHookInterface; stack-based self/total accounting
│   ├── ProfilingFn.php         AbstractFn proxy; times __invoke via session
│   ├── ReportFormat.php        Enum: Table, Json, Both (emitsTable/emitsJson predicates)
│   └── SortOrder.php           Enum: SelfTime, TotalTime, Calls, Avg
├── Infrastructure/
│   └── Command/
│       └── ProfileCommand.php  Symfony CLI command; wraps RunFacade::runFile/runNamespace
└── Gacela files
    ├── ProfileFacade
    ├── ProfileFactory
    ├── ProfileConfig
    └── ProfileProvider
```

## Dependencies

- **Run** (`RunFacade`): `runFile`, `runNamespace`, `autoDetectEntryPoint`, `writeLocatedException`, `writeStackTrace`
- **Shared** (`Munge`): namespace canonicalization
- **Lang** (`AbstractFn`, `ProfilerHookInterface`, `Registry`): fn proxy and hook installation

## Key Constraints

- Hook wraps fns at definition-time only; non-`AbstractFn` values pass through unchanged
- `ProfilingFn` extends `AbstractFn` so downstream `instanceof` checks succeed
- `ProfilingFn::__construct` copies inner meta; `getMeta()` and `withMeta()` work via `MetaTrait`
- Self time: maintain per-call stack, subtract child inclusive time from parent
- Self-recursive calls bypass proxy (`$this(...)` vs registry lookup); entry from outside counted, recursion depth not
- `ProfileCommand` installs hook before run, clears in `finally` block
