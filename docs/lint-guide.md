# Linter Guide

`phel lint` is a semantic linter that catches common mistakes without running code. It runs on source files or directories and emits diagnostics in human, JSON, or GitHub Actions format.

## Contents

- [Quickstart](#quickstart)
- [Rules](#rules)
- [Output formats](#output-formats)
- [Configuration](#configuration)
- [Cache](#cache)
- [Editor integration](#editor-integration)

## Quickstart

```bash
./vendor/bin/phel lint                    # lint configured source dirs
./vendor/bin/phel lint src/ tests/        # lint specific paths
./vendor/bin/phel lint --format=json      # machine-readable
./vendor/bin/phel lint --format=github    # CI annotations
```

## Rules

| Rule | Catches |
|------|---------|
| `unresolved-symbol` | reference to an unknown binding |
| `arity-mismatch` | call with wrong number of args |
| `unused-binding` | `let`/`fn` bindings never referenced |
| `unused-require` | imported ns never used |
| `unused-import` | imported PHP class never used |
| `shadowed-binding` | inner binding hides an outer one |
| `redundant-do` | `do` with a single child form |
| `duplicate-key` | map or let has the same key twice |
| `invalid-destructuring` | malformed destructure pattern |
| `discouraged-var` | use of deprecated or banned symbols |

## Output formats

- `human` (default): colored terminal output, suitable for local use
- `json`: array of `{file, line, column, rule, severity, message}` records
- `github`: `::error file=...` directives for GitHub Actions annotations

## Configuration

Drop a `phel-lint.phel` at your repo root:

```phel
{:rules
 {:unused-binding   {:enabled? true  :severity :warning}
  :shadowed-binding {:enabled? false}
  :discouraged-var  {:vars     ['println 'prn]}}
 :ignore-paths ["build/" "vendor/"]}
```

Pass a custom path with `--config=path/to/lint.phel`.

## Cache

Lint results are cached per file by content hash; subsequent runs only reanalyze changed files. Disable with `--no-cache`.

## Editor integration

Use `--format=json` from an editor plugin, or launch `phel lsp` for real-time diagnostics as you type.

## See also

- [LSP Guide](./lsp-guide.md)
- [Watch Guide](./watch-guide.md)
