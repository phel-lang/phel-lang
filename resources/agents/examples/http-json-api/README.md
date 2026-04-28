# HTTP JSON API

Three JSON endpoints. No persistence. Smallest possible web example. Flat layout, no `phel-config.php` (auto-detected).

## Run

```bash
composer install
./vendor/bin/phel test
php -S localhost:8000 -t public
```

## Endpoints

| Method | Path | Returns |
|--------|------|---------|
| GET | `/health` | `{:status "ok" :ts <unix>}` |
| GET | `/echo/{msg}` | `{:message "<msg>"}` |
| POST | `/sum` | `:parsed-body {:a 1 :b 2}` -> `{:sum 3}` |
