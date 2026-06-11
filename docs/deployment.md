# Deployment & Worker Runtimes

By default PHP is **shared-nothing**: every request boots a fresh process, so a
Phel namespace does not persist between requests. `phel build` compiles
namespaces to PHP ahead of time and [opcache](performance.md) caches that
bytecode, so nothing re-parses per request — but each request still re-runs
every loaded namespace's top-level forms to register its `def`s.

A **worker runtime** keeps the PHP process alive across requests: namespaces
load **once** at boot and in-memory state survives between requests, much closer
to the JVM/Clojure model.

## The one rule

Require the built entry point **once, before the request loop**. Everything
inside the loop should only call your exported functions.

```php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/build/app/main.php'; // loads Phel namespaces ONCE
```

See [Framework Integration](framework-integration.md) for `phel build` and
exporting PHP wrappers from Phel.

## FrankenPHP

`worker.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/build/app/main.php'; // once, outside the loop

$handler = static function (): void {
    // call an exported Phel wrapper per request
    echo \App\PhelGenerated\App\Main::handleRequest();
};

while (frankenphp_handle_request($handler)) {
    gc_collect_cycles();
}
```

Run it:

```bash
frankenphp php-server --root . --worker ./worker.php
```

> **State is per-worker.** FrankenPHP runs several worker instances, each with
> its own memory. An in-process value (an `atom`, a cache) is shared across
> requests handled by the *same* worker, not across all of them. For global
> state, use Redis/APCu/DB. Append `,1` to the worker path
> (`--worker ./worker.php,1`) to pin a single worker.

## RoadRunner

Same shape: require the built entry point once, then handle requests in the
worker loop (via `spiral/roadrunner-http`'s PSR-7 worker), calling exported Phel
functions per request.

## When you do not need a worker runtime

Plain PHP-FPM + opcache is fine for most apps. Reach for a worker runtime when
boot cost or per-request namespace registration shows up in profiling, or when
you want persistent in-memory state (caches, connection pools) across requests.
