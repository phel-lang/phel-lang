# Examples

Working projects agents can copy into a user workspace. Each directory is a complete, minimal Phel project: `phel-config.php`, source, tests, and when relevant a PHP entry point.

| Directory | What | Stack |
|-----------|------|-------|
| `todo-app/` | CRUD for todos over HTTP with in-memory atom store | `phel\router`, `phel\http`, `phel\json` |
| `http-json-api/` | Three JSON endpoints, no persistence | `phel\router`, `phel\http`, `phel\json` |
| `cli-wordcount/` | CLI that counts words from stdin or file args | `phel run`, PHP stdin |

## Running an example

```bash
cd todo-app
composer install
./vendor/bin/phel test
./vendor/bin/phel run src/entry.phel
```

HTTP examples serve on `php -S localhost:8000 -t public`. All examples use the flat layout (`src/`, `tests/`) and omit `phel-config.php` — the bundled `forProject()` auto-detects.

## CI

Each example ships with tests. Phase 6 wires a repo-level job that runs every example's test suite on PR so drift from the live language is caught early.
