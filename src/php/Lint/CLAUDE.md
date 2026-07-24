# Lint Module

Read-only semantic linter: emits diagnostics on Phel sources, never rewrites them.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `lint(list<string> $paths, RuleSettings $settings, ?LintCache $cache)` | `LintResult` |
| `loadSettings(string $configPath, RuleSettings $defaults)` | `RuleSettings` |
| `defaultSettings()` | `RuleSettings` |
| `formatters()` | `FormatterRegistry` |
| `createCache(string $baseDir, RuleSettings $settings)` | `LintCache` |

## Dependencies

| Facade | Used for |
|--------|----------|
| Api | `analyzeSource` (semantic diagnostics), `indexProject` |
| Compiler | `lexString`, `parseNext`, `read` |
| Command | default source directories |
| Run | `loadPhelNamespaces()` to ensure symbols resolve |

## CLI

`./bin/phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]`

Exit codes: `0` clean/warnings only, `1` errors, `2` invocation error.

## Rule Set (v1)

- Errors: `phel/unresolved-symbol`, `phel/arity-mismatch`, `phel/invalid-destructuring`, `phel/duplicate-key`
- Warnings: `phel/unused-binding`, `phel/unused-require`, `phel/unused-import`, `phel/shadowed-binding`, `phel/redundant-do`, `phel/discouraged-var`, `phel/comment-style`

Every shipped rule is on by default (it has an entry in `LintConfig::defaultSeverities()`); a rule with no entry there is off until a config opts it in.

Add a rule: implement `LintRuleInterface` in `Application/Rule/`, add a code constant to `RuleRegistry`, register it in `LintFactory::createRules()`, and give it a default severity in `LintConfig::defaultSeverities()`. Do not edit existing rules.

### `phel/comment-style`

Enforces the positional comment convention (`.claude/rules/phel.md`, shared with Clojure): `;` trails code on the same line, `;;` (or more) owns the whole line. Flags only a comment that starts a line and opens with exactly one `;`.

- `;;;`+ is clean — the rule asks that a whole-line comment is not written with the inline marker, and Clojure-style `;;;` section headers stay legal.
- Scans the **token stream**, not the source text: only the lexer knows which `;` opens a comment, so a `;` in a string literal, a regex literal, or a `#| ... |#` block can never be flagged.
- Bare `#` line comments are out of scope; the lexer already emits a deprecation for them.

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

## Output Formats

`human` (`file:line:col [severity] code message` + summary), `json` (stable array of `Diagnostic`), `github` (workflow annotations). Add one: implement `DiagnosticFormatterInterface`, register on `FormatterRegistry`.

## Key Constraints

- Read-only: never rewrites source; Formatter module owns whitespace/indent
- Semantic diagnostics (`unresolved-symbol`, `arity-mismatch`) come from `ApiFacade::analyzeSource` and are shared via `FileAnalysis::$semanticDiagnostics`, so the analyzer runs once per file
- Open/closed: `LintFactory::createRules()` and `FormatterRegistry` are the ONLY edit points for new rules/formatters
- `RulePipeline` isolates failing rules — one bad rule does not kill the run
- `DuplicateKeyRule` scans the parse tree, not read forms, because the reader silently deduplicates map literals
- Cache (default on, `.phel/lint-cache/index.json`): keyed by MD5(file hash) + rule fingerprint (all rule codes + severities + exclude patterns); adding/removing rules or editing `phel-lint.phel` invalidates it
