---
name: module-mutate
description: 'Load when changing src/php/Mutate: mutation testing for Phel code (`phel mutate`).'
scope: src/php/Mutate
---

# Mutate Module

Mutation testing for Phel `defn` / `defn-` forms (`phel mutate`). Infection covers `src/php/`; this covers `src/phel/` and user projects. Internal CLI plumbing, outside public semver (ADR 0021).

## Facade

Parent side: `plan()` -> `MutationPlan` (source files, worker load order, test namespaces), `generate()` -> mutants, `warm()` compiles the load order and flushes the cache index before workers spawn (no cold-cache race), `run()` -> `MutationReport` (throws `BaselineFailedException` on a red suite, `WorkerFailedException` when a worker cannot load). Worker side: `createWorkerSession()` for hidden `phel _mutate-worker`.

## Dependencies

| Facade | Parent | Worker |
|--------|--------|--------|
| `CompilerFacadeInterface` | `lexString()`, `parseAll()` | `getGlobalEnvironment()->setNs()` |
| `RunFacadeInterface` | `getDependenciesFromPaths()`, `getNamespaceFromFile()` | `evalFile()`, `eval()`, per-test coverage |
| `CommandFacadeInterface` | source and test directories | |

## Flow

- `MutantGenerator`: a site is any non-trivia child of any list in a definition body, seen through reader conditionals (`SemanticViews` caches the selected-branch view). Each mutant: one in-place `replaceChildren()`, `getCode()` of the top-level form, restore.
- `MutationPlanner`: `--changed` keeps files `Shared\Process\GitChangedFiles` reports. File-level on purpose: a mutant redefines the whole definition.
- `MutationRunner` (parent): a pool of `MutantWorker` subprocesses speaking `WorkerFrame`. Workers load and baseline at once, then take mutants as they free up; the first baseline is authoritative.
- `MutantWorkerSession` (worker): `load()` evaluates files under `*interactive-mode*`; `baseline()` runs the suite with per-test coverage when a driver exists (`file => line => tests`); `mutant()` picks tests reaching the definition's lines (`not-covered` if none, all if no attribution), evals the mutated form in its namespace, runs `run-tests` with `:only-tests`, evals the original back.

## Key Constraints

- **Mutate the parse tree, never source text.** `FileNode::getCode()` round-trips byte for byte. Mutators are pure: return a new child list, never call `replaceChildren()` (the generator does).
- **Never mutated:** definition head and name, docstring, attribute map (so not an `:inline` expander either), parameter vectors, anything under `'` or `` ` `` (data), `def`, `defmacro`.
- **Redefinition, not `with-redefs`.** Mutants compile in memory via `RunFacade::eval()`, never reaching the compiled-code cache.
- **Optimization level 0 only.** From level 1 the analyzer inlines callees, so a redefinition misses them and mutants survive wrongly (#3125, #3126). `MutateCommand` calls `Shared\OptimizationLevel::pin(0)` before anything compiles, overriding project config; the pin is an env var, so workers inherit it. The cache keys on the level (#3396).
- **Workers:** `--parallel=N|auto` (default 1; `auto` caps at 8). A silent worker scores `timeout` (min 1s, default 3x baseline), a dead one `killed`; a fresh worker (own load and baseline) replaces it. `MutantWorker::isDead()` tells a crash from slowness.
- **Coverage-guided when a driver exists** (xdebug coverage mode or pcov). Without one every mutant runs the whole suite, and the report says so. Attribution is per worker process. `PcovScopeWidener` adds `-d pcov.directory=/`; without it pcov instruments nothing outside `<cwd>/src` and every mutant reads `not-covered` (#3270).
- **Scores:** MSI = detected / (detected + survived + not-covered); covered MSI = detected / (detected + survived). All `not-covered` scores covered MSI 0, not 100 from 0/0; the text report warns when a driver reached no mutant. Mutants that do not compile are reported as errors and count in neither score. `--min-msi` / `--min-covered-msi` gate them; `.github/workflows/mutation-phel.yml` runs `src/phel/core` weekly against both.
- **The baseline must be green**, else `BaselineFailedException`, exit 1.
