# Linter Guide

`phel lint` catches common mistakes without running code. Accepts files or directories; outputs human, JSON, or GitHub Actions format.

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
| `phel/unresolved-symbol` | reference to an unknown binding |
| `phel/arity-mismatch` | call with wrong number of args |
| `phel/unused-binding` | `let`/`fn` bindings never referenced |
| `phel/unused-require` | imported ns never used |
| `phel/unused-import` | imported PHP class never used |
| `phel/shadowed-binding` | inner binding hides an outer one |
| `phel/redundant-do` | `do` with a single child form |
| `phel/duplicate-key` | map or let has the same key twice |
| `phel/invalid-destructuring` | malformed destructure pattern |
| `phel/discouraged-var` | use of deprecated or banned symbols |

## Output formats

- `human` (default): colored terminal output for local use
- `json`: array of `{file, line, column, rule, severity, message}` records
- `github`: `::error file=...` directives for GitHub Actions

## Configuration

Drop `phel-lint.phel` at the project root (resolved from the working directory). Pass a custom path with `--config=path/to/lint.phel`.

```phel
{:rules
 {:phel/unused-binding   :warning
  :phel/shadowed-binding :off
  :phel/arity-mismatch   :error}
 :exclude
 {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
```

Severities: `:error`, `:warning`, `:info`, `:hint`, `:off`. Patterns in `:exclude` match file paths (if containing `/` or `.phel`) or namespace names via `fnmatch`.

## Cache

Results are cached per file by content hash at `.phel/lint-cache/index.json`. Subsequent runs only reanalyze changed files. Adding/removing rules or editing `phel-lint.phel` invalidates the cache. Disable with `--no-cache`.

## Editor integration

Use `--format=json` from an editor plugin, or run `phel lsp` for live diagnostics.

## See also

- [LSP Guide](./lsp-guide.md)
- [Watch Guide](./watch-guide.md)
