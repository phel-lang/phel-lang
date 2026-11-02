---
name: module-mutate
description: 'Load when changing src/php/Mutate: mutation testing for Phel code (`phel mutate`).'
scope: src/php/Mutate
---

# Mutate Module

Mutation testing for Phel `defn` / `defn-` forms (`phel mutate`). Infection covers `src/php/`; this covers `src/phel/` and user projects, with no PHP dependency.

## Facade (internal CLI plumbing, outside public semver, ADR 0021)

| Method | Returns |
|--------|---------|
| `plan(MutateOptions)` | `MutationPlan`: source files, worker load order, test namespaces |
| `generate(MutationPlan, MutateOptions)` | `list<Mutant>` |
| `warm(MutationPlan)` | compiles the load order in the parent, flushes the cache index (no cold-cache race) |
| `run(MutationPlan, MutateOptions, list<Mutant>, ?Closure $onResult)` | `MutationReport`; throws `BaselineFailedException` (red suite) or `WorkerFailedException` (worker could not load) |
| `detectWorkerCount()` | `--parallel=auto` (`Shared\Process\CpuCountDetector`, capped at 8) |
| `createWorkerSession()` | `MutantWorkerSession`, worker side of `phel _mutate-worker` |

No `Phel\Shared\Facade` contract. No module imports `Mutate`; `Console` only wraps its commands.

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Compiler | `CompilerFacadeInterface` | `lexString()` + `parseAll()` (parent); `getGlobalEnvironment()->setNs()` (worker) |
| Run | `RunFacadeInterface` | `getDependenciesFromPaths()`, `getNamespaceFromFile()` (parent); `evalFile()`, `eval()`, `beginPerTestCoverage()` / `perTestCoverageByLine()` / `endPerTestCoverage()` (worker) |
| Command | `CommandFacadeInterface` | project source and test directories |

## Structure

- `Domain/Mutator/`: `MutatorInterface` (`id()`, `mutate(parent, index, child): list<Replacement>`), `MutatorRegistry` (built-ins, `--only`, unknown id throws), `Nodes`, `SymbolSwapTrait`, `Description`, and eleven mutators: `arith`, `compare`, `equality`, `logic`, `cond-branch`, `literal-bool`, `literal-num`, `literal-str`, `seq-op`, `return-nil`, `body-drop`.
- `Domain/`: `Mutant` (`diff()`, `lineRange()`), `MutantVerdict` (`killed | survived | error | timeout | not-covered`), `MutantResult`, `MutationReport` (MSI + covered MSI, coverage driver, `toText()`, `toJson()`), `MutateOptions`, `MutationPlan`, `TestsByLine`, exceptions.
- `Application/MutantGenerator`: a site is any non-trivia child of any list in a definition body. Each mutant: one in-place `replaceChildren()`, `getCode()` of the top-level form, restore.
- `Application/MutationPlanner`: `--changed` keeps files `Shared\Process\GitChangedFiles` reports. File-level on purpose: a mutant redefines the whole definition.
- `Application/ProjectWarmer` (parent): `evalFile` of the load order + `flushCompiledCodeCache()` before workers spawn.
- `Application/PcovScopeWidener`: `-d pcov.directory=/` for worker argv when pcov is loaded, else `[]`.
- `Application/MutationRunner` (parent): pool of `MutantWorker` subprocesses (`WorkerFrame` over stdin/stdout, `send()` / `tryReceive()` with a deadline). Workers load and baseline at once, then take mutants as they free up; the first baseline is authoritative.
- `Application/MutantWorkerSession` (worker): `load()` evaluates files, enables `*interactive-mode*`. `baseline()` runs the suite under `beginPerTestCoverage()` when a driver exists, keeping `file => line => tests`. `mutant()` picks tests reaching the definition's line range (`not-covered` if none, all if no attribution), `setNs` + eval mutated form, `run-tests` with `:only-tests`, eval original.
- `Infrastructure/Command/`: `MutateCommand` (`mutate`), `MutateWorkerCommand` (`_mutate-worker`, hidden).

## Key Constraints

- **Mutate the parse tree, never source text.** `FileNode::getCode()` round-trips byte for byte. Mutators are pure: return a new child list, never call `replaceChildren()` (the generator does).
- **Never mutated:** definition head and name, docstring, attribute map, parameter vectors, anything under `'` or `` ` `` (data), `def`, `defmacro`, `:inline` bodies (a redefinition would not reach call sites).
- **Mutants run in worker subprocesses (`--parallel=N|auto`, default 1).** A silent worker scores `timeout` (min 1s, default 3x baseline), a dead one `killed`; a fresh worker (own load + baseline) replaces it. `MutantWorker::isDead()` tells crash from slowness.
- **Coverage-guided when a driver exists** (xdebug coverage mode or pcov). Each mutant runs only tests reaching its definition; unreached is `not-covered` without running. No driver: every mutant runs the whole suite, and the report says so. Attribution is per worker process (the collector hooks that process's `phel.test`). Without the `PcovScopeWidener` `-d`, pcov instruments nothing outside `<cwd>/src` and every mutant reads `not-covered` (#3270).
- **MSI** = detected / (detected + survived + not-covered). **Covered MSI** = detected / (detected + survived). `--min-msi` and `--min-covered-msi` gate them; `.github/workflows/mutation-phel.yml` runs `src/phel/core` weekly against both. All-`not-covered` scores covered MSI 0 (not 100 from 0/0); the text report warns when a driver reached no mutant.
- **Redefinition, not `with-redefs`.** The worker evaluates the whole mutated `defn` in its namespace under `*interactive-mode*`, then the original. Mutants compile in memory via `RunFacade::eval()`, never reaching the compiled-code cache.
- **Optimization level 0 only.** From level 1 the analyzer inlines callees, so a redefinition misses them and mutants survive wrongly (#3125, #3126). `MutateCommand` pins 0 via `Shared\OptimizationLevel::pin()` before anything compiles, overriding project config. The pin is an env var, so workers inherit it. The cache keys on the level (#3396).
- **The baseline must be green**, else `BaselineFailedException`, exit 1.
- **Errors** (mutants that do not compile) are reported but count in neither score.
