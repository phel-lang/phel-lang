# Examples

Runnable projects agents can copy into a user workspace.

| Directory | What | Stack |
|-----------|------|-------|
| `todo-app/` | HTTP CRUD with in-memory atom store | `phel\router`, `phel\http`, `phel\json` |
| `http-json-api/` | Three JSON endpoints, no persistence | `phel\router`, `phel\http`, `phel\json` |
| `cli-wordcount/` | Counts words from stdin or file args | `phel run`, PHP stdin |

## Run

```bash
cd todo-app
composer install
./vendor/bin/phel test
./vendor/bin/phel run src/entry.phel
```

HTTP examples serve on `php -S localhost:8000 -t public`. Flat layout (`src/`, `tests/`), no `phel-config.php` — `forProject()` auto-detects.

`composer test-agents` runs every example's tests on PR against the local source.
