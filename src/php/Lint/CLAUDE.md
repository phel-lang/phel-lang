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
| Compiler | `CompilerFacadeInterface` | `readFormsBestEffort` (`SourceReader`); `lexString`, `parseNext`, `read` (`ConfigLoader`, `DuplicateKeyRule` — both need the failures reported, not swallowed) |
| Command | `CommandFacadeInterface` | default source directories |
| Run | `RunFacadeInterface` | `loadPhelNamespaces()` to ensure symbols resolve |

All four getters return the Shared contract, never a concrete facade, and `SatelliteFactoryFacadeInjectionTest` pins the return types. The diagnostics Lint consumes (`Phel\Shared\Api\Diagnostic`, `ProjectIndex`, `Definition`, `Location`) are Shared value objects, so Lint's own rules never name a `Phel\Api` class.

## CLI

`./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]`

Exit codes: `0` clean/warnings only, `1` errors, `2` invocation error.

## Rule Set (v1)

- Errors: `phel/unresolved-symbol`, `phel/arity-mismatch`, `phel/invalid-destructuring`, `phel/duplicate-key`, `phel/duplicate-def`
- Warnings: `phel/unused-binding`, `phel/unused-require`, `phel/unused-import`, `phel/shadowed-binding`, `phel/redundant-do`, `phel/discouraged-var`, `phel/comment-style`

Every shipped rule is on by default (it has an entry in `LintConfig::defaultSeverities()`); a rule with no entry there is off until a config opts it in.

Add a rule: implement `LintRuleInterface` in `Application/Rule/`, add a code constant to `RuleRegistry`, register it in `LintFactory::createRules()`, and give it a default severity in `LintConfig::defaultSeverities()`. Do not edit existing rules.

### Shared rule helpers (`Application/Rule/`)

`FormWalker`, `FnParamVectors`, `NamespaceForm`, `NsClauseIterator`, plus:

- `ForHead`: parses a `for`/`dofor` head. That head is a sequence of `binding :verb coll-expr` triples with `:while`/`:when`/`:let` modifiers and a `:reduce [acc init]` option; it is NOT a `let`-style pair list. Any rule that reads it two-at-a-time mistakes the collection expression for a bound name. Returns each bound form paired with the head forms in which a reference to it counts as a use.
- `SymbolAlias`: the implicit alias of a `(:use ...)` / `(:require ...)` entry with no `:as`. Splits on both `.` and `\`, because Phel accepts both separators and the analyzer treats them alike.

### `phel/duplicate-def`

Flags a top-level symbol defined twice in the same file. Works off the file's
own read forms, never the runtime registry, so the verdict does not depend on
what the linting process happens to have loaded. A forward `(declare foo)`
followed by the real definition stays clean; `defonce` and `defmethod` are
excluded by design.

The analyzer's own `DuplicateDefinitionException` cannot cover this: it only
fires once the namespace has actually been evaluated, which a compile-only
lint pass never does.

### `phel/comment-style`

Enforces the positional comment convention (`.claude/rules/phel.md`, shared with Clojure): `;` trails code on the same line, `;;` (or more) owns the whole line. Flags only a comment that starts a line and opens with exactly one `;`.

- `;;;`+ is clean — the rule asks that a whole-line comment is not written with the inline marker, and Clojure-style `;;;` section headers stay legal.
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
- A missing config file means defaults. A file that exists but is unreadable, unparseable, or not a map raises `Domain\Exception\LintConfigException` and `phel lint` exits 2 — never silently falls back to defaults
- A collected `.phel` file that cannot be read raises `Domain\Exception\LintSourceException` — never skipped, which would report it as clean and exit 0. A listed **directory** that cannot be walked raises the same exception (`cannotWalkDirectory`, chaining the iterator's `UnexpectedValueException`): yielding zero files there is the identical silent pass

## Output Formats

`human` (`file:line:col [severity] code message` + summary), `json` (stable array of `Diagnostic`), `github` (workflow annotations). Add one: implement `DiagnosticFormatterInterface`, register on `FormatterRegistry`.

## Key Constraints

- Read-only: never rewrites source; Formatter module owns whitespace/indent
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) come from `ApiFacadeInterface::analyzeSource` and are shared via `FileAnalysis::$semanticDiagnostics`, so the analyzer runs once per file
- Open/closed: `LintFactory::createRules()` and `FormatterRegistry` are the ONLY edit points for new rules/formatters
- `RulePipeline` isolates failing rules — one bad rule does not kill the run
- `DuplicateKeyRule` scans the parse tree, not read forms, because the reader silently deduplicates map literals
- Cache (default on, `.phel/lint-cache/index.json`): keyed by MD5(file hash) + rule fingerprint (all rule codes + severities + exclude patterns); adding/removing rules or editing `phel-lint.phel` invalidates it
