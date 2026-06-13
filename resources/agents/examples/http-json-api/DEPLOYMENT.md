# Deploying a Phel app to production

This guide uses the `http-json-api` example, but the flow is the same for any
Phel project.

## 1. Build ahead of time

In development `\Phel::run(...)` compiles `.phel` on the fly. In production you
compile once and ship the generated PHP so the compiler never runs on a
request:

```bash
composer install --no-dev --optimize-autoloader
./vendor/bin/phel build --report
```

`phel build` writes compiled PHP into the output directory (default `out/`).
`--report` prints the namespace count, per-namespace compiled size, total size,
and build time so you can spot bloat and confirm CI produced the artifact:

```
Namespaces: 37 (37 fresh, 0 cached) | Total: 1.75 MB | Time: 240.3 ms | Output: out
```

## 2. Minimal runtime dependency surface

A built app needs only:

- PHP 8.4+
- `phel-lang/phel-lang` (a normal `require`, not `require-dev`) — it provides
  the runtime (persistent data types, the `\Phel` bootstrap, core library)
- your `vendor/` installed with `--no-dev`

You do **not** need dev tools (PHPUnit, PHPStan, cs-fixer) at runtime. Verify
with a clean install:

```bash
composer install --no-dev
./vendor/bin/phel build
php -S localhost:8000 -t public   # serves without dev dependencies
```

## 3. Opcache

Compiled Phel output is plain PHP, so opcache caches it like any other file.
Recommended production settings:

```ini
opcache.enable=1
opcache.validate_timestamps=0   ; immutable image: never stat files
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.preload=/app/out/main.php   ; optional: preload the built entry
```

With `validate_timestamps=0` you must rebuild the image (or `opcache_reset()`)
to pick up new code — exactly what you want for an immutable deploy.

## 4. Docker (multi-stage)

See the `Dockerfile` next to this guide. The build stage installs Composer
dependencies and runs `phel build`; the runtime stage copies only PHP, the
built output, and the no-dev `vendor/`:

```bash
docker build -t my-phel-api .
docker run --rm -p 8000:8000 my-phel-api
curl localhost:8000/health
```

## 5. nginx + php-fpm

Point php-fpm at `public/index.php` and let nginx pass through:

```nginx
server {
    listen 80;
    root /app/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Each request boots fresh: `index.php` requires the built output, opcache keeps
it hot. No per-request compilation once the project is built.

## 6. Worker mode (FrankenPHP / RoadRunner)

Worker mode keeps the PHP process alive across requests for big speedups, but
Phel runtime state is **process-global**: the `\Phel\Lang\Registry` (definition
table) and the global compiler environment persist between requests. That is
safe for code (definitions don't change at runtime), but be careful with:

- **Mutable globals/atoms** defined at the top level — they keep their value
  across requests; reset per-request state yourself.
- **Dynamic vars** (`set-var`, `binding`) — always restore them in a `finally`
  so a request cannot leak bound values into the next.
- **Request data** — never stash it in a top-level `def`; pass it through the
  handler chain.

A minimal FrankenPHP worker boots the app once, then loops handling requests:

```php
<?php // worker.php
require __DIR__ . '/vendor/autoload.php';
$handler = static function (): void {
    require __DIR__ . '/public/index.php'; // built output, no compiler
};
while (\frankenphp_handle_request($handler)) {
    gc_collect_cycles();
}
```

Treat top-level side effects as one-time boot work and keep handlers pure over
the request/response values, and worker mode is safe.
