---
name: module-shared
description: 'Load when changing Shared: facade interfaces, constants, cross-module value objects, pure utilities.'
scope: src/php/Shared
---

# Shared Module

Leaf contract layer: facade interfaces, constants, cross-module value objects, pure stateless utilities. No Gacela pattern. Facade contract rules: `.agnostic-ai/rules/module-map.md`.

## Key Constraints

- Contracts and utilities only, never business logic. Utilities stay stateless and safe to instantiate without module context.
- A value object lives here so a facade contract need not name a foreign module's class (a cycle).
- "Shared so X and Y cannot drift" below means: give neither consumer its own copy.
- Public vs internal (ADR 0021): the Api and Run contracts are internal; other facade contracts follow the public embedding policy. Internal classes and methods carry `@internal`; `tests/php/Unit/Architecture/InternalAnnotationTest.php` checks the annotation against `tests/php/Support/PublicApiSurface.php` (split documented in `docs/stability.md`).

## Compiler back-edge (accepted cycle, #2785)

`Facade/CompilerFacadeInterface` is the only Shared -> Compiler edge. It imports 9 `Phel\Compiler\Domain` symbols: 4 in method signatures (`AbstractNode`, `NodeEnvironmentInterface`, `GlobalEnvironmentInterface`, `TokenStream`), 5 only in `@throws`. `tests/php/Unit/Architecture/SharedCompilerBoundaryTest.php` fails on a second edge or a changed symbol set; widen it only deliberately.

- Do not try to remove it: the SCC breaks only if all 9 go, which means moving the analyzer AST into Shared. Untyped handles lose PHPStan L9 / Psalm L1 safety; narrow AST interfaces restate the node hierarchy.
- It is benign: `use` statements only, no load or build-order effect; Shared never instantiates a compiler type.

## Constants

- `CompilerConstants::DEFAULT_SOURCE` (`'string'`): `CompilerFacadeInterface` defaults to it so it need not reference `Application\Lexer`.
- `ReplConstants::REPL_MODE`: set by `phel repl` only; carries the `phel.repl` refer injection. `INTERACTIVE_MODE`: set by `phel repl`, `phel eval` and the nREPL server; disables the duplicate-definition guard.

## Value objects and parse model

- `Eval/EvalResult`: returned by `RunFacadeInterface::structuredEval()`; orchestration lives in `Run\Application\StructuredEvaluator`.
- `Api/*`: only logic is `toArray()` and `ProjectIndex`'s pure queries.
- `Formatter/FormatResult`: separate changed and failed lists so `phel format` exits non-zero on an unparsable file.
- `Parser/Node/*`: parse-tree contract for Formatter, Lint, Api, produced by Compiler. `AbstractTriviaNode`'s constructor is `final`, so a trivia subclass carries no state.

## Utilities: traps and single sources

| Class | What to know |
|-------|--------------|
| `FloatFormatter` | finite float round-trip text, restoring the caller's `serialize_precision`; shared by compiler emission and runtime printing |
| `OptimizationLevel` | `resolve()` returns the configured level unless `PHEL_OPTIMIZATION_LEVEL` is set; `pin()` sets that env var so subprocesses inherit it (`phel mutate` pins 0) |
| `FrameworkNamespaces` | `clojureTarget()`: `clojure.X` -> `phel.X`, `clojure.set` -> `phel.core`, null outside `clojure.*`. Shared so Build's dependency walk and `ns` check, the analyzer alias remap and lint `phel/unresolved-namespace` cannot drift |
| `ExistingPaths` | `reportMissing()` names each missing typed path on stderr; the command then returns `Command::INVALID` (2), so a typed path never narrows silently |
| `InvocationError` | every "could not run as asked" message goes through `report()`: stderr + exit 2, so stdout keeps only command output |
| `NumericOption` | numeric CLI options throw `InvalidArgumentException` naming the option, reported via `InvocationError` |
| `EnvVar` | the one reader of Phel env switches. `flag()` / `integer()` return null when unset or empty, else throw with the name; `isOn()` for foreign variables (`CI`) never throws. `ConsoleBootstrap::doRun()` checks Phel switches up front, `bin/phel` the OPcache ones, both exit 2 |
| `StandardError` | the console's stderr. In machine modes (`--format=json`, `analyze`, `api-daemon`, TAP, JUnit XML) stdout carries only machine output |
| `PhelProjectDirectory` | `.phel/` resolution order: `PHEL_DIR` -> `withPhelDir()` -> `<projectRoot>/.phel`. `resolveCacheDir()` (`PHEL_CACHE_DIR` first) is the single source for Build's and Compiler's cache dir, so Compiler needs no reference to Build. `opcachePath()` skips `withPhelDir()` on purpose: `bin/phel` resolves the OPcache file cache before config loads, and `cache:clear` must target the same path |
| `VersionFinder` / `VersionResolver` | `LATEST_VERSION` is bumped by `tools/release.sh` and is only the last release tag. `VersionResolver::current()` caches the running version per process (beta commit suffix included); build stamps, runtime guards and compiled-code cache identity use it |
| `CompiledSourceHash` | cache key: `xxh128` of source plus `\|O{level}` (level > 0) and `\|E{fingerprint}`. Changing it needs an `INDEX_FORMAT_VERSION` bump. Shared so Build's `FileEvaluator` (writer) and `SecondaryFileHarvester` (reader) key identically |
| `CompileOptions` | `emitAsExpression`: analyse top-level forms in expression context so a folded pure value is not dropped (`phel compile`) |
| `Binding/IterationHead` | bound names of a `for`/`dofor`/`foreach` head. Not `let`-style pairs: a pairwise read yields the collection expression as a name. Shared so Lint's binding rules and Api's `PointCompleter` cannot drift |
| `TagResolver` | `:tag` meta -> PHP type string; single source for analyzer type inference and the emitters. Only the compiler's `TagCanonicalizer` calls `expandImports()`, so tags on the AST and in definition meta are already canonical |
| `LintRuleCodes` | public API: `phel lint --format=json` writes them to `code` (gated by `PublicApiSurfaceTest`). `allCodes()` omits `INTERNAL_ERROR` (never configurable) and the deprecated `DUPLICATE_KEY` |
| `ProjectRootResolver` | anchors to the working directory, not the binary, so global and PHAR installs read the project's config (#2640) |
| `NoColor` | `followOutput()` lets the console's own decoration decision override `NO_COLOR` (set by `ConsoleBootstrap`) |
| `Lint/LintRuleExplainerInterface` | lets Run's `phel explain` use Lint's catalog (Lint already depends on Run) |
| `DeprecationResolver` | shared by Api's `SymbolExtractor` and Lint's `discouraged-var`. The compiler's `DeprecatedDefinitionWarner` keeps its own reading on purpose: it warns on any truthy value |

## Subdirectory rules

`Exceptions/`, `SourceMap/`, `Performance/` + `Process/` and `Printer/` each have a `module-shared-<part>.md` rule that loads with its directory.
