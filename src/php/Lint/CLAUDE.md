# Lint Module

Read-only semantic linter built on top of `ApiFacade`: emits diagnostics on Phel sources without rewriting files.

## Gacela Pattern

- **Facade**: `LintFacade` extends `AbstractFacade<LintFactory>`
- **Factory**: `LintFactory` extends `AbstractFactory<LintConfig>`
- **Config**: `LintConfig` — default severities, cache dir, config filename
- **Provider**: `LintProvider` — injects `FACADE_API`, `FACADE_COMPILER`, `FACADE_COMMAND`

## Public API (Facade)

- `lint(list<string> $paths, RuleSettings $settings, ?LintCache $cache): LintResult`
- `loadSettings(string $configPath, RuleSettings $defaults): RuleSettings`
- `defaultSettings(): RuleSettings`
- `formatters(): FormatterRegistry`
- `createCache(string $baseDir): LintCache`

## CLI Command

`./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]`

Exit codes: `0` = clean or warnings only, `1` = one or more errors, `2` = invocation error.

## Rule Set (v1)

- `phel/unresolved-symbol` (error)
- `phel/arity-mismatch` (error)
- `phel/invalid-destructuring` (error)
- `phel/duplicate-key` (error)
- `phel/unused-binding` (warning)
- `phel/unused-require` (warning)
- `phel/unused-import` (warning)
- `phel/shadowed-binding` (warning)
- `phel/redundant-do` (warning)
- `phel/discouraged-var` (warning)

Each rule is a single `LintRuleInterface` class in `Application/Rule/`. Adding a rule is `new RuleClass()` in `LintFactory::createRules()` plus a code constant in `RuleRegistry` — no edits to existing rules.

## Config File

`phel-lint.phel` in the project root (override with `--config`). EDN-like Phel map parsed by the existing reader:

```phel
{:rules {:phel/unused-binding :off
         :phel/arity-mismatch :error}
 :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`. Exclude patterns match file path when they contain `/` or `.phel`, otherwise they match the namespace name via `fnmatch`.

## Cache

Optional, enabled by default. Stores per-file diagnostics under `.phel/lint-cache/index.json` keyed by MD5 file hash + rule fingerprint. Disable with `--no-cache`.

## Output Formats

- `human` — `file:line:col [severity] code message` plus a summary line
- `json` — stable JSON array of `Diagnostic` objects
- `github` — `::warning file=X,line=Y,col=Z,title=CODE::message` annotations

Formatters implement `DiagnosticFormatterInterface` and are registered on `FormatterRegistry`.

## Dependencies

- **Api** (`ApiFacade`) — `analyzeSource`, `indexProject`
- **Compiler** (`CompilerFacade`) — `lexString`, `parseNext`, `read`
- **Command** (`CommandFacade`) — default source directories

## Structure

```
Lint/
|-- Application/
|   |-- Cache/           LintCache (file-hash + rule-fingerprint keyed JSON index)
|   |-- Config/          RuleRegistry, RuleSettings, ConfigLoader
|   |-- Formatter/       HumanFormatter, JsonFormatter, GithubFormatter, FormatterRegistry
|   |-- Rule/            One class per rule + FormWalker + DiagnosticBuilder
|   |-- FileCollector, SourceReader, RulePipeline, LintRunner
|-- Domain/              LintRuleInterface, DiagnosticFormatterInterface, FileAnalysis
|-- Infrastructure/
|   +-- Command/         LintCommand (Symfony console)
|-- Transfer/            LintResult
+-- Gacela files         LintFacade, LintFactory, LintConfig, LintProvider
```

## Key Constraints

- Lint is **read-only**: never rewrites source; `fmt` owns whitespace/indent
- Semantic diagnostics (`unresolved-symbol`, analyzer-detected `arity-mismatch`) are fetched via `ApiFacade::analyzeSource` and shared across rules through `FileAnalysis::$semanticDiagnostics` so the analyzer runs once per file
- Rule pipeline is open/closed: `LintFactory::createRules()` is the only edit point to add a rule
- `FormatterRegistry` is also open/closed: register new formatter names without editing existing ones
- `LintCache` fingerprint is derived from `RuleRegistry::allCodes()` — adding/removing rules auto-invalidates the cache
- Failing rules are isolated in `RulePipeline`: one bad rule never kills the run
- `DuplicateKeyRule` scans the parse tree (not read forms) because the reader silently de-duplicates map literals
