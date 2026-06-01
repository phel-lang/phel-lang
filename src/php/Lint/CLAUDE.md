# Lint Module

Read-only semantic linter emitting diagnostics on Phel sources without rewriting.

## Gacela Pattern

- **Facade**: `LintFacade`
- **Factory**: `LintFactory`
- **Config**: `LintConfig` (default severities, cache dir, config filename)
- **Provider**: `LintProvider` (injects `FACADE_API`, `FACADE_COMPILER`, `FACADE_COMMAND`, `FACADE_RUN`)

## Public API (Facade)

| Method | Signature |
|--------|-----------|
| `lint` | `(list<string> $paths, RuleSettings $settings, ?LintCache $cache): LintResult` |
| `loadSettings` | `(string $configPath, RuleSettings $defaults): RuleSettings` |
| `defaultSettings` | `(): RuleSettings` |
| `formatters` | `(): FormatterRegistry` |
| `createCache` | `(string $baseDir, RuleSettings $settings): LintCache` |

## CLI Command

```
./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]
```

Exit codes: 0 = clean/warnings only; 1 = errors; 2 = invocation error.

## Rule Set (v1)

| Code | Default |
|------|---------|
| `phel/unresolved-symbol` | error |
| `phel/arity-mismatch` | error |
| `phel/invalid-destructuring` | error |
| `phel/duplicate-key` | error |
| `phel/unused-binding` | warning |
| `phel/unused-require` | warning |
| `phel/unused-import` | warning |
| `phel/shadowed-binding` | warning |
| `phel/redundant-do` | warning |
| `phel/discouraged-var` | warning |

To add a rule: create `LintRuleInterface` class in `Application/Rule/`, add code constant to `RuleRegistry`, instantiate in `LintFactory::createRules()`. Do not edit existing rules.

## Config File

File: `phel-lint.phel` (override via `--config`). Phel map parsed by reader:

```phel
{:rules {:phel/unused-binding :off
         :phel/arity-mismatch :error}
 :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`. Patterns match file path (if contains `/` or `.phel`) or namespace name via `fnmatch`.

## Cache

Optional, enabled by default. Stores per-file diagnostics at `.phel/lint-cache/index.json` keyed by MD5(file hash) + rule fingerprint. Disable with `--no-cache`.

## Output Formats

| Format | Output |
|--------|--------|
| `human` | `file:line:col [severity] code message` plus summary |
| `json` | JSON array of `Diagnostic` objects (stable) |
| `github` | `::warning file=X,line=Y,col=Z,title=CODE::message` annotations |

Registered on `FormatterRegistry`, implement `DiagnosticFormatterInterface`.

## Dependencies

| Module | Via | Purpose |
|--------|-----|---------|
| Api | `ApiFacade` | `analyzeSource` (semantic diagnostics), `indexProject` |
| Compiler | `CompilerFacade` | `lexString`, `parseNext`, `read` |
| Command | `CommandFacade` | Default source directories |
| Run | `RunFacade` | `loadPhelNamespaces()` ensures symbols are resolved |

## Structure

```
Lint/
|-- Application/
|   |-- Cache/           LintCache (MD5 hash + rule-fingerprint keyed JSON)
|   |-- Config/          RuleRegistry, RuleSettings, ConfigLoader
|   |-- Formatter/       HumanFormatter, JsonFormatter, GithubFormatter, FormatterRegistry
|   |-- Rule/            10 rule classes; FormWalker, DiagnosticBuilder, utilities
|   +-- FileCollector, SourceReader, RulePipeline, LintRunner
|-- Domain/              LintRuleInterface, DiagnosticFormatterInterface, FileAnalysis
|-- Infrastructure/Command/   LintCommand (Symfony console)
|-- Transfer/            LintResult
+-- Gacela              LintFacade, LintFactory, LintConfig, LintProvider
```

## Key Constraints

- Read-only: never rewrites source (fmt owns whitespace/indent)
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) shared via `FileAnalysis::$semanticDiagnostics` so analyzer runs once per file via `ApiFacade::analyzeSource`
- Rule pipeline open/closed: `LintFactory::createRules()` only edit point for new rules
- `FormatterRegistry` open/closed: register new formatters without editing existing ones
- Cache fingerprint = MD5(all rule codes + severity + exclude patterns); adding/removing rules or editing `phel-lint.phel` invalidates cache
- `RulePipeline` isolates failing rules; one bad rule does not kill run
- `DuplicateKeyRule` scans parse tree (not read forms); reader silently deduplicates map literals
