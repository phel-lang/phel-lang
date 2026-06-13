# HTTP JSON API

Three JSON endpoints. No persistence. Smallest possible web example. Flat layout, no `phel-config.php` (auto-detected).

## Run

```bash
composer install
./vendor/bin/phel test
php -S localhost:8000 -t public
```

## Build & deploy

```bash
./vendor/bin/phel build --report   # compile ahead of time + size/timing summary
```

See [DEPLOYMENT.md](DEPLOYMENT.md) for the production story: build flow, opcache,
the included multi-stage [Dockerfile](Dockerfile), nginx + php-fpm, and
worker-mode (FrankenPHP) caveats.

## Endpoints

| Method | Path | Returns |
|--------|------|---------|
| GET | `/health` | `{:status "ok" :ts <unix>}` |
| GET | `/echo/{msg}` | `{:message "<msg>"}` |
| POST | `/sum` | `:parsed-body {:a 1 :b 2}` -> `{:sum 3}` |
