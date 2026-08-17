# Mutate Module

Mutation testing for Phel code (`phel mutate`): every `defn` / `defn-` under the
given paths is changed one small, plausible mistake at a time, the test suite
runs against each mutant, and the ones the suite does not catch are listed.
Infection covers `src/php/`; this covers `src/phel/` and any user's Phel
project, with no PHP dependency added.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `plan(MutateOptions)` | `MutationPlan`: source files to mutate, worker load order, test namespaces |
| `generate(MutationPlan, MutateOptions)` | `list<Mutant>` from the selected mutators |
| `run(MutationPlan, MutateOptions, list<Mutant>, ?Closure $onResult)` | `MutationReport`; throws `BaselineFailedException` (red suite) or `WorkerFailedException` (worker could not load) |
| `detectWorkerCount()` | what `--parallel=auto` means here (`Shared\Process\CpuCountDetector`, capped at 8) |
| `createWorkerSession()` | `MutantWorkerSession`, the worker side of the protocol for `phel _mutate-worker` |

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Compiler | `CompilerFacadeInterface` | `lexString()` + `parseAll()` (parent); `getGlobalEnvironment()->setNs()` (worker) |
| Run | `RunFacadeInterface` | `getDependenciesFromPaths()`, `getNamespaceFromFile()` (parent); `evalFile()`, `eval()`, `beginPerTestCoverage()` / `perTestCoverageByLine()` / `endPerTestCoverage()` (worker) |
| Command | `CommandFacadeInterface` | project source and test directories |

No module imports `Mutate`; `Console` only wraps its commands. No
`Phel\Shared\Facade` contract, like `Balance`, `Lint`, `Lsp`, `Nrepl`,
`Profile` and `Watch`.

## Structure

- `Domain/Mutator/`: `MutatorInterface` (`id()`, `mutate(parent, index, child): list<Replacement>`), `MutatorRegistry` (all built-ins, `--only` selection, unknown id throws), `Nodes` (replacement atoms + child-list edits), `SymbolSwapTrait`, `Description`, and the eleven mutators: `arith`, `compare`, `equality`, `logic`, `cond-branch`, `literal-bool`, `literal-num`, `literal-str`, `seq-op`, `return-nil`, `body-drop`.
- `Domain/`: `Mutant` (file, ns, definition, line, form line, mutator, description, original + mutated form, `diff()`, `lineRange()`), `MutantVerdict` (`killed | survived | error | timeout | not-covered`), `MutantResult`, `MutationReport` (totals, MSI + covered MSI, coverage driver, `toText()`, `toJson()`), `MutateOptions`, `MutationPlan`, exceptions.
- `Application/MutantGenerator`: walks the CST; sites are every non-trivia child of every list inside a definition body; each mutant is one `replaceChildren()` in place, `getCode()` of the whole top-level form, then the original children back.
- `Application/MutationPlanner`: options + project config to `MutationPlan`; `--changed` keeps only the source files `Shared\Process\GitChangedFiles` reports (file-level: a changed line invalidates what its whole definition does, and the definition is what a mutant redefines).
- `Application/MutationRunner` (parent): a pool of `MutantWorker`s (`phel _mutate-worker` subprocesses, `WorkerFrame` over stdin/stdout, `send()` / `tryReceive()` with a deadline). All workers load and baseline at once, then mutants are handed out as workers come free; the first worker's baseline is authoritative.
- `Application/MutantWorkerSession` (worker): `load()` evaluates the files and switches on `*interactive-mode*`; `baseline()` runs the suite under `beginPerTestCoverage()` when a driver is available and keeps `file => line => tests`; `mutant()` = pick the tests whose coverage reaches the definition's line range (`not-covered` when none, run everything when no attribution), `setNs` + eval mutated form, `run-tests` with `:only-tests`, eval original form.
- `Infrastructure/Command/`: `MutateCommand` (`mutate`), `MutateWorkerCommand` (`_mutate-worker`, hidden).

## Key Constraints

- **Mutate the parse tree, never the source text.** `FileNode::getCode()` round-trips byte for byte, so the only textual difference between original and mutant is the mutation. Mutators are pure: build a new child list, never call `replaceChildren()` yourself; the generator does, and restores.
- **Not mutated on purpose:** definition head and name, docstring and attribute map, parameter vectors, anything under `'` or `` ` `` (data, not code), `def`, `defmacro`, `:inline` bodies (they are inlined at call sites, a redefinition would not reach them).
- **Mutants run in worker subprocesses (`--parallel=N|auto`, default 1).** In-process would be faster but a mutant that loops forever or blows the stack has to cost one worker, not the run: the parent scores a silent worker as `timeout` (never below 1s, default 3x the baseline) and a dead one as `killed`, then spawns a fresh worker (load + baseline on its own) and goes on. `MutantWorker::isDead()` tells a crash from a slow answer.
- **Coverage-guided when a driver is there.** Every worker takes the baseline under per-test coverage attribution (`RunFacadeInterface::beginPerTestCoverage()`, xdebug in coverage mode or pcov), so each mutant runs only the tests whose coverage reaches its definition, and a definition no test reaches is `not-covered` without running anything. Without a driver every mutant runs the whole suite and the report says so. Attribution is per worker process because the collector hooks that process's `phel.test`; the baselines run in parallel so start-up costs one baseline of wall time, not N.
- **MSI vs covered MSI.** MSI = detected / (detected + survived + not-covered): what nothing tests counts against the suite. Covered MSI = detected / (detected + survived): the quality of the tests that exist. `--min-msi` and `--min-covered-msi` gate each; `.github/workflows/mutation-phel.yml` runs `src/phel/core` weekly against both floors.
- **Redefinition, not `with-redefs`.** The worker evaluates the whole mutated `defn` in its namespace under `*interactive-mode*` (the same switch `phel.repl` and the nREPL use), then evaluates the original again. That handles every `defn` shape (docstring, meta, multi-arity, pre/post) without rebuilding a `fn`. Compile of a mutant goes through `RunFacade::eval()`, in memory: nothing reaches the compiled-code cache.
- **Optimization level 0 only.** At `-O2` `CallInliner` inlines callees into already-compiled callers, so redefining a var does not reach them and every such mutant would survive for the wrong reason (#3125, #3126).
- **The baseline must be green.** A red suite scores nothing; `BaselineFailedException` ends the run with exit 1.
- **Errors** (mutants that do not compile) are reported but count in neither score.
