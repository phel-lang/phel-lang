---
name: module-lint
description: 'Lint module: Read-only semantic linter.'
scope: src/php/Lint
---

# Lint Module

Read-only semantic linter: emits diagnostics on Phel sources, never rewrites them.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `lint(list<string> $paths, RuleSettings $settings, ?LintCache $cache)` | `LintResult`; throws `LintSourceException` on an unreadable file or directory |
| `loadSettings(string $configPath, RuleSettings $defaults)` | `RuleSettings` |
| `defaultSettings()` | `RuleSettings` |
| `formatters()` | `FormatterRegistry` |
| `createCache(string $baseDir, RuleSettings $settings)` | `LintCache` |

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Api | `ApiFacadeInterface` | `analyzeSource` (semantic diagnostics), `indexProject` |
| Compiler | `CompilerFacadeInterface` | `readFormsBestEffort` (`SourceReader`); `lexString`, `parseNext`, `read` (`ConfigLoader`, `DuplicateKeyRule`, both need the failures reported, not swallowed) |
| Command | `CommandFacadeInterface` | default source directories |
| Run | `RunFacadeInterface` | `loadPhelNamespaces()` to ensure symbols resolve |

All four getters return the Shared contract, never a concrete facade, and `SatelliteFactoryFacadeInjectionTest` pins the return types. The diagnostics Lint consumes (`Phel\Shared\Api\Diagnostic`, `ProjectIndex`, `Definition`, `Location`) are Shared value objects, so Lint's own rules never name a `Phel\Api` class.

## CLI

`./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]`

Exit codes: `0` clean/warnings only, `1` errors (including `phel/internal-error`), `2` invocation error.

## Rule Set (v1)

- Errors: `phel/unresolved-symbol`, `phel/unresolved-namespace`, `phel/arity-mismatch`, `phel/invalid-destructuring`, `phel/duplicate-key`, `phel/duplicate-def`
- Warnings: `phel/unused-binding`, `phel/unused-require`, `phel/unused-import`, `phel/shadowed-binding`, `phel/shadowed-core-fn`, `phel/redundant-do`, `phel/discouraged-var`, `phel/comment-style`

Every shipped rule is on by default (it has an entry in `LintConfig::defaultSeverities()`); a rule with no entry there is off until a config opts it in.

Add a rule: implement `LintRuleInterface` in `Application/Rule/`, add a code constant to `Shared\LintRuleCodes`, register it in `LintFactory::createRules()`, and give it a default severity in `LintConfig::defaultSeverities()`. Do not edit existing rules.

### `phel/internal-error` (not a rule)

The code `RulePipeline` reports under when a rule's `apply()` throws. It is the
one diagnostic the linter emits about itself, so it plays by different rules
from the fourteen above:

- **Always `error` severity**, never `RuleSettings::severityFor()`. A configured
  severity grades a finding about the linted code; a crash is a finding about
  the linter. Honouring a `:warning` there would exit 0 with the rule's real
  findings missing, which is exactly the silent pass it exists to prevent.
- **Not in `LintRuleCodes::allCodes()` and not in `defaultSeverities()`**, so it
  has nothing to configure, contributes nothing to the cache fingerprint, and
  cannot be switched off from `phel-lint.phel`.
- **Anchored at line 1, col 1** of the file being analysed: a crash has no
  source location. `Domain\Exception\LintRuleException::ruleCrashed()` owns the
  wording, names the rule code, and chains the original throwable.
- **Never cached** (`LintRunner`): fixing a rule changes neither the file hash
  nor the rule fingerprint, so a cached internal error would outlive the bug.

The rest of the pipeline still runs, so the other rules' findings for that file
are reported alongside it. The escape hatch is the explicit one: set the
crashing rule to `:off`, which skips it before `apply()` is ever reached.

### Shared rule helpers (`Application/Rule/`)

`FormWalker`, `FnParamVectors`, `NamespaceForm`, `NsClauseIterator`, plus:

- `SymbolAlias`: the implicit alias of a `(:use ...)` / `(:require ...)` entry with no `:as`. Splits on both `.` and `\`, because Phel accepts both separators and the analyzer treats them alike.

`Phel\Shared\Binding\IterationHead` parses the `for`/`dofor`/`foreach` heads for the binding rules. It lives in Shared because Api's `PointCompleter` reads the same heads; see `.agnostic-ai/rules/module-shared.md`.

### `phel/unresolved-namespace`

Flags a `(:require ...)` of a `phel.*` namespace, or a `clojure.*` one whose
`phel.*` target (`FrameworkNamespaces::clojureTarget`), that no source, test or
vendor directory declares, with the closest known name as a suggestion. The
known set comes from `RunFacadeInterface::getAllNamespaces()`
(`Infrastructure\ProjectKnownNamespaces`), read once per run. A user namespace
is out of scope: linting a file outside the configured dirs would flag its
siblings, and the runtime already names a missing one.

### `phel/duplicate-def`

Flags a top-level symbol defined twice in the same file. Works off the file's
own read forms, never the runtime registry, so the verdict does not depend on
what the linting process happens to have loaded. A forward `(declare foo)`
followed by the real definition stays clean; `defonce` and `defmethod` are
excluded by design.

The analyzer's own `DuplicateDefinitionException` cannot cover this: it only
fires once the namespace has actually been evaluated, which a compile-only
lint pass never does.

### `phel/shadowed-core-fn`

Flags a `let`/`loop`/`if-let`/`when-let`, `fn`/`defn`/`defmacro` parameter or
`for`/`dofor`/`foreach` binding whose name is a public, non-macro `phel.core`
function. Only plain symbols count; names bound by destructuring stay clean.

The core names come from the runtime registry (`Infrastructure\RegistryCoreFunctionNames`),
read once per run, because the lint command loads `phel.core` before any rule
runs. Nothing loaded means an empty set, never a crash.

The repo's own `phel-lint.phel` excludes `*/src/phel/*`: the stdlib's arglists
(`name`, `key`, `val`, `rest`, ...) mirror Clojure's and are what `phel doc`
prints, so renaming them to satisfy the rule would change the documented
signatures.

### `phel/comment-style`

Enforces the positional comment convention (`.agnostic-ai/rules/phel.md`, shared with Clojure): `;` trails code on the same line, `;;` (or more) owns the whole line. Flags only a comment that starts a line and opens with exactly one `;`.

- `;;;`+ is clean; the rule asks that a whole-line comment is not written with the inline marker, and Clojure-style `;;;` section headers stay legal.
- Scans the **token stream**, not the source text: only the lexer knows which `;` opens a comment, so a `;` in a string literal, a regex literal, or a `#| ... |#` block can never be flagged.
- Bare `#` line comments are out of scope; the lexer already emits a deprecation for them.

### `phel/discouraged-var`

Flags uses of definitions carrying `:deprecated` metadata, read from
`ProjectIndex` (`Definition::isDeprecated()`, populated by `SymbolExtractor`)
for the rest of the project and from the file's own forms for the file being
linted, since the index does not cover it.

Docstring prose is never a marker: a docstring that documents a `:deprecated`
map key, or warns about a deprecated PHP builtin, says nothing about the
definition it documents. The defining form's own name symbol is skipped, so
deprecating something does not flag its declaration.

## Config File

`phel-lint.phel` (override via `--config`). Phel map parsed by the reader:

```phel
{:rules {:phel/unused-binding :off
         :phel/arity-mismatch :error}
 :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

- Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`
- Exclude patterns match file path (when they contain `/` or `.phel`) or namespace name, via `fnmatch`
- A missing config file means defaults. A file that exists but is unreadable, unparseable, or not a map raises `Domain\Exception\LintConfigException` and `phel lint` exits 2; never silently falls back to defaults
- A collected `.phel` file that cannot be read raises `Domain\Exception\LintSourceException`; never skipped, which would report it as clean and exit 0. A listed **directory** that cannot be walked raises the same exception (`cannotWalkDirectory`, chaining the iterator's `UnexpectedValueException`): yielding zero files there is the identical silent pass

## Output Formats

`human` (`file:line:col [severity] code message` + summary), `json` (stable array of `Diagnostic`), `github` (workflow annotations). Add one: implement `DiagnosticFormatterInterface`, register on `FormatterRegistry`.

## Key Constraints

- Read-only: never rewrites source; Formatter module owns whitespace/indent
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) come from `ApiFacadeInterface::analyzeSource` and are shared via `FileAnalysis::$semanticDiagnostics`, so the analyzer runs once per file
- Open/closed: `LintFactory::createRules()` and `FormatterRegistry` are the ONLY edit points for new rules/formatters
- `RulePipeline` isolates a failing rule without silencing it: the run continues, but a `phel/internal-error` diagnostic makes it exit 1 rather than report the file as clean (see above)
- `DuplicateKeyRule` scans the parse tree, not read forms, because the reader silently deduplicates map literals
- Cache (default on, `.phel/lint-cache/index.json`): keyed by MD5(file hash) + `LintCacheFingerprint` (Phel release + all rule codes + severities + exclude patterns); upgrading Phel, adding/removing rules or editing `phel-lint.phel` invalidates it. A file whose run produced a `phel/internal-error` is not cached at all
- The rule-level `catch (Throwable)` in `CommentStyleRule` and `DuplicateKeyRule` exists for a source that does not lex or parse, not as a licence to swallow rule bugs. Because it catches `Throwable` around the whole `apply()` body, a genuine bug in either rule still bypasses `RulePipeline`'s guard; narrowing both to the documented lexer/parser exceptions is open work
- A file that does not lex or parse is reported with the analyzer's own code (`PHEL310`, `PHEL100`, ...) and fails the run. `readFormsBestEffort` is still best-effort, but its `Generator::getReturn()` now says whether anything was dropped; `SourceReader` passes that through as `SourceRead::$failed`, and `LintRunner` emits the `analyzeSource` diagnostics for such a file. Rules still see the forms that did read (#3292)
- A superseded form (`PHEL012`: `php/new`, `php/->`, `php/::`, `set-var`) is passed through from `analyzeSource` by `LintRunner` under the analyzer's code, like a syntax error: it stops `phel run`, so no rule can switch it off (#3456)
- An `ns` form the analyzer rejects is reported the same way, with the analyzer's code (`PHEL007` for `:refer :all`): `LintRunner` keeps the `analyzeSource` diagnostics that fall inside the `ns` form, since no rule owns them. `LintCommand` loads the Phel namespaces inside its `try`, so a failure there is a `Lint failed:` line, not a bare console error (#3457)
