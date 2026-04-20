# Todo app

Minimal HTTP todo API. In-memory atom store. Shows routing, middleware, JSON responses, and tests. Flat layout, no `phel-config.php` (auto-detected).

## Run

```bash
composer install
./vendor/bin/phel test
php -S localhost:8000 -t public
```

## Endpoints

| Method | Path | Body | Returns |
|--------|------|------|---------|
| GET | `/todos` | - | `{:todos [...]}` |
| POST | `/todos` | `{"title":"..."}` | `{:id N :title "..."}` |
| GET | `/todos/{id}` | - | `{:id N :title "..."}` or 404 |
| DELETE | `/todos/{id}` | - | 204 |

## Files

```
composer.json              # phel-lang/phel-lang only
public/index.php           # \Phel::run bootstraps and runs the entry ns
src/store.phel             # atom-backed CRUD
src/handlers.phel          # request to response fns
src/routes.phel            # route tree + app-handler
src/entry.phel             # build request, call handler, emit response
tests/handlers_test.phel
tests/store_test.phel
```

## Design notes

- Atom `(def store (atom {:next 1 :items {}}))` — single source of state.
- Handlers read `(get request :parsed-body {})`; production would add a JSON-decode middleware.
- Route tree is data, no macros at call site.
- Middleware logs method and status per request.
