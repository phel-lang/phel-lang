---
name: module-lint
description: 'Lint module: Read-only semantic linter.'
scope: src/php/Lint
---

# Lint Module

## Internal tooling API (Facade)

| Method | Returns |
|--------|---------|
| `lint(list<string> $paths, RuleSettings $settings, ?LintCache $cache)` | `LintResult`; throws `LintSourceException` on an unreadable file or directory |
| `lintSource(string $source, string $path, RuleSettings $settings)` | `LintResult` for supplied buffer text, including a path that does not exist |
| `loadSettings(string $configPath, RuleSettings $defaults)` | `RuleSettings` |
| `defaultSettings()` | `RuleSettings` |
| `formatters()` | `FormatterRegistry` |
| `createCache(string $baseDir, RuleSettings $settings)` | `LintCache` |
| `ruleExplainer()` | `Shared\Lint\LintRuleExplainerInterface`, implemented by `Domain\LintRuleCatalog`, for `phel explain` |

Internal plumbing, outside the public semver surface (ADR 0021).

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Api | `ApiFacadeInterface` | `analyzeSource` (semantic diagnostics), `indexProject` |
| Compiler | `CompilerFacadeInterface` | `readFormsBestEffort` (`SourceReader`); `lexString`, `parseNext`, `read` (`ConfigLoader`, `CommentStyleRule`, both need failures reported, not swallowed) |
| Command | `CommandFacadeInterface` | default source directories |
| Run | `RunFacadeInterface` | `loadPhelNamespaces()` so symbols resolve |

Getters return the Shared contract, never a concrete facade (`SatelliteFactoryFacadeInjectionTest`). Rules use Shared value objects (`Phel\Shared\Api\Diagnostic`, `ProjectIndex`, `Definition`, `Location`), never a `Phel\Api` class.

## CLI

`./bin/phel lint [paths]... [--format=text|json|github] [--config=path] [--no-cache]`

Exit codes: `0` clean/warnings only, `1` errors (including `phel/internal-error`), `2` invocation error.

## Rule Set (v1)

- Errors: `phel/unresolved-symbol`, `phel/unresolved-namespace`, `phel/unresolved-refer`, `phel/arity-mismatch`, `phel/invalid-destructuring`, `phel/duplicate-def`, `phel/compile-error`
- Warnings: `phel/unused-binding`, `phel/unused-require`, `phel/unused-import`, `phel/shadowed-binding`, `phel/shadowed-core-fn`, `phel/redundant-do`, `phel/discouraged-var`, `phel/comment-style`, `phel/unknown-class`

A rule is on by default only with an entry in `LintConfig::defaultSeverities()`; otherwise a config must opt it in.

Add a rule: implement `LintRuleInterface` in `Application/Rule/`, add a constant to `Shared\LintRuleCodes`, register it in `LintFactory::createRules()`, add its default severity. Do not edit existing rules.

### `phel/internal-error` (not a rule)

Reported by `RulePipeline` when a rule's `apply()` throws; a finding about the linter, not the code:

- **Always `error`**, never `RuleSettings::severityFor()`: a configured `:warning` would exit 0 with findings missing, the silent pass it prevents.
- **Not in `LintRuleCodes::allCodes()` or `defaultSeverities()`**: not configurable, not fingerprinted, cannot be switched off.
- **Anchored at 1:1**. `Domain\Exception\LintRuleException::ruleCrashed()` owns the wording, names the rule, chains the throwable.
- **Never cached** (`LintRunner`): fixing a rule changes neither file hash nor fingerprint, so a cached one would outlive the bug.

Other rules still run for that file. Escape hatch: set the crashing rule to `:off`.

### Shared rule helpers (`Application/Rule/`)

`FormWalker`, `FnParamVectors`, `NamespaceForm`, `NsClauseIterator`, plus `SymbolAlias`: the implicit alias of a `(:use ...)` / `(:require ...)` entry with no `:as`. Splits on both `.` and `\` (both are separators).

`Phel\Shared\Binding\IterationHead` parses `for`/`dofor`/`foreach` heads; it lives in Shared because Api's `PointCompleter` reads them too.

### `phel/unresolved-namespace`

Flags a `(:require ...)` of a `phel.*` namespace, or a `clojure.*` one whose `phel.*` target (`FrameworkNamespaces::clojureTarget`), that no source, test or vendor directory declares, suggesting the closest name. Known set: `RunFacadeInterface::getAllNamespaces()` via `Infrastructure\ProjectKnownNamespaces`, read once per run. User namespaces are out of scope: a file outside the configured dirs would flag its siblings.

### `phel/unresolved-refer`

Promotes the analyzer's `PHEL013` (`NsSymbol`: a `:refer` names something the required namespace does not define or keeps private). The analyzer checks only loaded namespaces, so the Api analysis stage loads the file's dependencies first. `definterface`/`defstruct` names count as defined via their PHP class.

### `phel/compile-error`

Reports every error-severity analyzer diagnostic no dedicated rule promotes, so a file `phel run` rejects never lints clean. `errorCode` keeps the `PHELxxx` code.

- A rule that promotes an analyzer code implements `Domain\PromotingRuleInterface`; `CompileErrorRule::besides()` reads the promoted codes off `LintFactory::createRules()`, so a new promoting rule cannot double-report.
- `LintRunner` drops a `phel/compile-error` when an error from a pass-through (syntax error, `PHEL012`, rejected `ns` form) or another rule starts inside its span. The analyzer stops at a form's first error, so it is the same mistake: `(let [a] a)` is reported once, by `phel/invalid-destructuring`.
- The analysis never evaluates a `def`, so it cannot expand a call to a macro the file defines, and a macro that runs the caller's code while expanding meets a fn that is still `null`. `ReadAndAnalyzeStage` drops those errors rather than report a `PHEL005` the compiler would not raise; see `.agnostic-ai/rules/module-api.md`.

### `phel/duplicate-def`

Flags a top-level symbol defined twice in a file. Uses the file's read forms, never the runtime registry, so the verdict does not depend on what is loaded. `declare` then definition is clean; `defonce`/`defmethod` are excluded by design. The analyzer's `DuplicateDefinitionException` cannot replace it: it fires only on evaluation, which lint never does.

### `phel/shadowed-core-fn`

Flags a `let`/`loop`/`if-let`/`when-let`, `fn`/`defn`/`defmacro` parameter or `for`/`dofor`/`foreach` binding named after a public, non-macro `phel.core` function. Only plain symbols count, not destructured names.

Core names come from the runtime registry (`Infrastructure\RegistryCoreFunctionNames`), read once per run; nothing loaded means an empty set, never a crash.

The repo's `phel-lint.phel` excludes `*/src/phel/*`: stdlib arglists (`name`, `key`, `val`, `rest`, ...) mirror Clojure's and are what `phel doc` prints; do not rename them.

### `phel/comment-style`

Positional comments (`.agnostic-ai/rules/phel.md`): flags only a line-starting comment opening with exactly one `;`.

- `;;;`+ is clean (Clojure section headers).
- Scans the **token stream**, not source text, so a `;` in a string, regex, or `#| ... |#` block is never flagged.
- Bare `#` comments are out of scope; the lexer deprecates them.

### `phel/discouraged-var`

Flags uses of definitions with `:deprecated` metadata, read from `ProjectIndex` (`Definition::isDeprecated()`, filled by `SymbolExtractor`) for the project and from the file's own forms for the linted file, which the index does not cover.

Docstring prose is never a marker (it may document a `:deprecated` key or a deprecated PHP builtin). The defining form's own name is not flagged.

### `phel/unknown-class`

Warns on a static call `(Foo/bar ...)` whose class cannot be autoloaded, after resolving `\Foo`, `Foo.Bar` and `:use` aliases. A warning because a class can be loaded at runtime. Skipped: lowercase namespaces without a leading `\` (`\vendor\Foo/run` is checked), `:require` aliases, and classes the file declares (`defstruct`, `definterface`, ...). A Java class adds its Phel replacement from `ClassNotFoundHint::javaClassHint()` (#3465).

## Config File

`phel-lint.phel` (override via `--config`), a Phel map parsed by the reader:

```phel
{:rules {:phel/unused-binding :off
         :phel/arity-mismatch :error}
 :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

- Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`
- Exclude patterns match the file path (when they contain `/` or `.phel`) or the namespace, via `fnmatch`
- Missing file means defaults; an unreadable, unparseable or non-map file raises `Domain\Exception\LintConfigException` (exit 2), never a silent fallback
- An unreadable `.phel` file, or a listed **directory** that cannot be walked (`cannotWalkDirectory`, chaining `UnexpectedValueException`), raises `Domain\Exception\LintSourceException`; never skip either, which would report clean and exit 0

## Output Formats

- `text`: `file:line:col [severity] code message` + summary; `human` is still accepted. `json`: stable array of `Diagnostic`. `github`: workflow annotations, `file=` relative to the working directory.
- Lines and columns are 1-based: rules build diagnostics through `DiagnosticBuilder` or `Diagnostic::fromSourceSpan()`, the one place a 0-based `SourceLocation` column gains its `+ 1`.
- `json` adds `errorCode` (promoted `PHELxxx`, else `null`), `suggestions` and `fix` (`RulePipeline` fills these from `ErrorCodeCatalog`, or `Domain\LintRuleCatalog` for lint-only rules).
- Fields are only ever added: the codes are public API (#3315). A new cached field bumps `LintCacheFingerprint::ENTRY_FORMAT`.
- Add a formatter: implement `DiagnosticFormatterInterface`, register on `FormatterRegistry`.

## Key Constraints

- Never rewrites source; the Formatter module owns whitespace/indent
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) come from `ApiFacadeInterface::analyzeSource`, shared via `FileAnalysis::$semanticDiagnostics`, so the analyzer runs once per file
- `LintRunner::runSource()` uses the same reader, analysis and rule pipeline for supplied text, preserving path-based settings without collecting or reading source files, indexing the workspace, or using the lint disk cache. Dependency preparation may still inspect saved-path context.
- Open/closed: `LintFactory::createRules()` and `FormatterRegistry` are the ONLY edit points for new rules/formatters
- A repeated key in a map or set literal is the reader's `PHEL203`; no lint rule repeats it. `LintRuleCodes::DUPLICATE_KEY` stays as a deprecated constant because the class is public API
- Cache (default on, `.phel/lint-cache/index.json`): keyed by MD5(file hash) + `LintCacheFingerprint` (Phel release + all rule codes + severities + exclude patterns); so upgrading Phel or editing rules or `phel-lint.phel` invalidates it
- `CommentStyleRule`'s `catch (Throwable)` is for unlexable sources, not a licence to swallow rule bugs; it wraps all of `apply()`, so a bug there bypasses `RulePipeline`'s guard. Narrowing it to lexer/parser exceptions is open work
- A file that does not lex or parse fails the run under the analyzer's code (`PHEL310`, `PHEL100`, ...): `readFormsBestEffort`'s `Generator::getReturn()` flags dropped forms, `SourceReader` exposes it as `SourceRead::$failed`, and `LintRunner` emits the `analyzeSource` diagnostics. Rules still see the forms that read (#3292)
- A superseded form (`PHEL012`: `php/new`, `php/->`, `php/::`, `set-var`) is passed through by `LintRunner` under the analyzer's code, like a syntax error: it stops `phel run`, so no rule can switch it off (#3456)
- An `ns` form the analyzer rejects is reported the same way (`PHEL007` for `:refer :all`): `LintRunner` keeps the `analyzeSource` diagnostics inside the `ns` form that no dedicated rule reported; `phel/compile-error` yields to them. `LintCommand` loads namespaces inside its `try`, so a failure is a `Lint failed:` line (#3457)
