# Lint Module

Read-only semantic linter emitting diagnostics on Phel sources without rewriting.

## Public API (Facade)

- `lint(list<string> $paths, RuleSettings $settings, ?LintCache $cache): LintResult`
- `loadSettings(string $configPath, RuleSettings $defaults): RuleSettings`, `defaultSettings()`
- `formatters(): FormatterRegistry`, `createCache(string $baseDir, RuleSettings $settings): LintCache`

## CLI

`./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]`

Exit codes: 0 = clean/warnings only; 1 = errors; 2 = invocation error.

## Rule Set (v1)

Errors: `phel/unresolved-symbol`, `phel/arity-mismatch`, `phel/invalid-destructuring`, `phel/duplicate-key`.
Warnings: `phel/unused-binding`, `phel/unused-require`, `phel/unused-import`, `phel/shadowed-binding`, `phel/redundant-do`, `phel/discouraged-var`.

To add a rule: create `LintRuleInterface` class in `Application/Rule/`, add code constant to `RuleRegistry`, instantiate in `LintFactory::createRules()`. Do not edit existing rules.

## Config File

`phel-lint.phel` (override via `--config`). Phel map parsed by reader:

```phel
{:rules {:phel/unused-binding :off
         :phel/arity-mismatch :error}
 :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`. Patterns match file path (if contains `/` or `.phel`) or namespace name via `fnmatch`.

## Output Formats

`human` (`file:line:col [severity] code message` + summary), `json` (stable array of `Diagnostic`), `github` (workflow annotations). Registered on `FormatterRegistry`; implement `DiagnosticFormatterInterface`.

## Dependencies

Api (`analyzeSource` semantic diagnostics, `indexProject`), Compiler (`lexString`, `parseNext`, `read`), Command (default source directories), Run (`loadPhelNamespaces()` ensures symbols resolved).

## Key Constraints

- Read-only: never rewrites source (fmt owns whitespace/indent)
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) shared via `FileAnalysis::$semanticDiagnostics` so analyzer runs once per file via `ApiFacade::analyzeSource`
- Open/closed: `LintFactory::createRules()` and `FormatterRegistry` are the only edit points for new rules/formatters
- Cache (default on, `.phel/lint-cache/index.json`): keyed by MD5(file hash) + rule fingerprint (all rule codes + severity + exclude patterns); adding/removing rules or editing `phel-lint.phel` invalidates
- `RulePipeline` isolates failing rules; one bad rule does not kill run
- `DuplicateKeyRule` scans parse tree (not read forms); reader silently deduplicates map literals
