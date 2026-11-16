---
name: module-lint
description: 'Load when changing the Lint module: rules, config, output formats, lint cache.'
scope: src/php/Lint
---

# Lint Module

Read-only semantic linter (`phel lint`). Facade is internal tooling, outside public semver (ADR 0021); no Shared contract, so Lsp holds the one concrete binding (`.agnostic-ai/rules/module-map.md`).

## Dependencies

| Facade | Used for |
|--------|----------|
| `ApiFacadeInterface` | `analyzeSource` (semantic diagnostics), `indexProject` |
| `CompilerFacadeInterface` | `readFormsBestEffort` (`SourceReader`); `lexString`, `parseNext`, `read` (`ConfigLoader`, `CommentStyleRule`: both must report failures, not swallow them) |
| `CommandFacadeInterface` | default source directories |
| `RunFacadeInterface` | namespace resolution and dependency metadata |

Rules use Shared value objects (`Phel\Shared\Api\*`), never a `Phel\Api` class.

## CLI and config

- Exit codes: `0` clean or warnings only, `1` errors (including `phel/internal-error`), `2` invocation or config error.
- `phel-lint.phel` (or `--config`) is a Phel map: `{:rules {:phel/x :off} :exclude {:phel/x ["src/a.phel" "phel.experimental.*"]}}`. Severities `:error :warning :info :hint :off`. An exclude pattern matches the file path when it contains `/` or `.phel`, else the namespace, via `fnmatch`.
- A missing config means defaults. An unreadable, unparseable or non-map config raises `LintConfigException` (exit 2), never a silent fallback.
- An unreadable `.phel` file, or a listed directory that cannot be walked, raises `LintSourceException`. Never skip either: it would report clean and exit 0.

## Adding rules and formatters

- `LintFactory::createRules()` and `FormatterRegistry` are the ONLY edit points.
- New rule: implement `LintRuleInterface` in `Application/Rule/`, add a constant to `Shared\LintRuleCodes`, register it, add its default severity. A rule is on by default only with an entry in `LintConfig::defaultSeverities()`.
- A rule that promotes an analyzer code implements `Domain\PromotingRuleInterface`; `CompileErrorRule::besides()` reads the promoted codes off `createRules()`, so it cannot double-report.
- Build diagnostics through `DiagnosticBuilder` or `Diagnostic::fromSourceSpan()`, the one place a 0-based `SourceLocation` column gains its `+ 1`.
- Output: `json` fields are public API (#3315), only add fields. A new cached field bumps `LintCacheFingerprint::ENTRY_FORMAT`. `RulePipeline` fills `suggestions` and `fix` from `ErrorCodeCatalog`, or `Domain\LintRuleCatalog` for lint-only rules. `text` still accepts the old name `human`; `github` writes `file=` relative to the working directory.

## `phel/internal-error` (not a rule)

`RulePipeline` reports it when a rule's `apply()` throws.

- Always `error`, never `RuleSettings::severityFor()`: a configured `:warning` would exit 0 with findings missing.
- Not in `allCodes()` or `defaultSeverities()`: not configurable, not fingerprinted. Escape hatch: set the crashing rule to `:off`. Other rules still run for that file.
- Anchored at 1:1; `LintRuleException::ruleCrashed()` owns the wording.
- Never cached (`LintRunner`): fixing a rule changes neither file hash nor fingerprint.
- `CommentStyleRule`'s `catch (Throwable)` wraps all of `apply()` (meant for unlexable sources), so a bug there bypasses this guard. Narrowing it is open work.

## Analyzer pass-through

- The analyzer runs once per file: `analyzeSource` results are shared via `FileAnalysis::$semanticDiagnostics`.
- A file that does not lex or parse fails the run under the analyzer's code (`PHEL310`, `PHEL100`, ...): `readFormsBestEffort`'s `Generator::getReturn()` flags dropped forms, `SourceRead::$failed` exposes it, `LintRunner` emits the diagnostics. Rules still see the forms that read (#3292).
- A superseded form (`PHEL012`: `php/new`, `php/->`, `php/::`, `set-var`) and an `ns` form the analyzer rejects (`PHEL007` for `:refer :all`) pass through the same way: they stop `phel run`, so no rule can switch them off.
- `phel/compile-error` reports every other error-severity analyzer diagnostic, so a file `phel run` rejects never lints clean. `LintRunner` drops it when a pass-through or another rule's error starts inside its span (the analyzer stops at a form's first error): `(let [a] a)` is reported once, by `phel/invalid-destructuring`.
- Analysis cannot expand a macro the file itself defines; see `.agnostic-ai/rules/module-api.md`.
- `LintCommand` loads namespaces inside its `try`, so a failure is a `Lint failed:` line.
- `LintRunner::runSource()` lints supplied text (LSP buffers) with the same pipeline and path-based settings, but without collecting files, indexing the workspace or using the disk cache.

## Rule notes

- `unresolved-namespace`: only `phel.*` requires, or `clojure.*` ones via `FrameworkNamespaces::clojureTarget`. Known set from `RunFacadeInterface::getAllNamespaces()`, read once per run. User namespaces are out of scope (a file outside the configured dirs would flag its siblings).
- `unresolved-refer`: promotes the analyzer's `PHEL013`, which checks only loaded namespaces, so the Api analysis loads the file's dependencies first. `definterface`/`defstruct` names count as defined via their PHP class.
- `duplicate-def`: uses the file's read forms, never the runtime registry; the analyzer's `DuplicateDefinitionException` fires only on evaluation. `declare` then definition is clean; `defonce`/`defmethod` are excluded.
- `shadowed-core-fn`: plain symbols only, not destructured names. Core names from the runtime registry (`RegistryCoreFunctionNames`); nothing loaded means an empty set, never a crash. The repo's `phel-lint.phel` excludes `*/src/phel/*`: stdlib arglists (`name`, `key`, `val`, `rest`) mirror Clojure's and are what `phel doc` prints. Do not rename them.
- `comment-style`: flags only a line-starting comment with exactly one `;` (`.agnostic-ai/rules/phel.md`); `;;;`+ is clean. Scans the token stream, so a `;` inside a string or regex is never flagged.
- `discouraged-var`: `:deprecated` metadata from `ProjectIndex` for the project and from the file's own forms for the linted file (the index does not cover it). Docstring prose is never a marker.
- `unknown-class`: a warning because a class can load at runtime. Skips lowercase namespaces without a leading `\`, `:require` aliases and classes the file declares. A Java class adds its Phel replacement from `ClassNotFoundHint::javaClassHint()`.
- `SymbolAlias`: the implicit alias of a `(:use ...)` / `(:require ...)` entry with no `:as` splits on both `.` and `\`.
- A repeated map or set key is the reader's `PHEL203`; no lint rule repeats it. `LintRuleCodes::DUPLICATE_KEY` stays deprecated because the class is public API.

## Cache

Default on, `.phel/lint-cache/index.json`. Keyed by file hash, transitive dependency and referenced project-index source hashes, and `LintCacheFingerprint` (Phel release, rule codes, severities, exclude patterns). `LintCacheDependencies` reuses Run's dependency resolver; `LintCacheLoadedSources` follows literal `load` paths via `Shared\LoadedSourceLocator`. A failed walk, unreadable source, computed load path or namespace switch bypasses the cache. A miss replays semantic analysis for its cached indexed providers before running rules; cached diagnostics bypass the rule pipeline.

Never rewrite source: the Formatter owns whitespace and indent.
