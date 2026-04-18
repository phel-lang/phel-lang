# Framework Integration Recipes

How to drop Phel into an existing PHP project without fighting the framework's conventions.

## The core idea

Phel reads one file at your project root: `phel-config.php`. Point `setSrcDirs()` at a directory that does **not** collide with your framework's PHP tree (`app/`, `src/`, whatever), and you are done. Phel only scans `.phel` files, so in principle it can share a dir with PHP, but a dedicated `phel/` folder keeps things tidy and discoverable.

Calling Phel from PHP has two flavors:

| Flavor | How | When |
|--------|-----|------|
| **Exported wrappers** | Mark fns `{:export true}`, run `vendor/bin/phel export`, call the generated PHP class | Typed, IDE-friendly, stable surface |
| **Dynamic lookup** | `\Phel::bootstrap($root)` then `\Phel::getDefinition($ns, $name)(...)` | Prototyping, scripting, one-offs |

All recipes below assume:

```bash
composer require phel-lang/phel-lang
```

---

## Laravel

Laravel owns `app/`. Keep Phel out of it.

### Directory layout

```
my-laravel-app/
├── app/
│   ├── ...                # Laravel (untouched)
│   └── PhelGenerated/     # Generated PHP wrappers (auto-created by phel export)
├── phel/                  # Phel sources
│   └── pricing.phel
├── tests-phel/            # Phel tests
├── phel-config.php
└── composer.json
```

Putting the generated wrappers inside `app/` means Laravel's default `App\` PSR-4 entry autoloads them without extra config.

### `phel-config.php`

```php
<?php

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests-phel'])
    ->setFormatDirs(['phel', 'tests-phel'])
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['phel'])
        ->setNamespacePrefix('App\\PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/app/PhelGenerated'));
```

Add `app/PhelGenerated/` to `.gitignore` if you prefer regenerating on deploy instead of committing the wrappers.

### Write and export a fn

`phel/pricing.phel`:

```phel
(ns pricing)

(defn apply-discount
  {:export true}
  [price percent]
  (- price (* price (/ percent 100))))
```

```bash
vendor/bin/phel export
```

### Call from a controller

```php
use App\PhelGenerated\Pricing;

final class CheckoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $final = Pricing::applyDiscount(
            (float) $request->input('price'),
            (float) $request->input('percent'),
        );

        return response()->json(['total' => $final]);
    }
}
```

Add a `composer scripts` hook so exports stay in sync:

```json
"scripts": {
    "phel:export": "phel export",
    "post-autoload-dump": ["@phel:export"]
}
```

---

## Symfony

Symfony owns `src/`. Same pattern: new `phel/` folder.

### Directory layout

```
my-symfony-app/
├── src/
│   ├── ...                # Symfony (untouched)
│   └── PhelGenerated/     # Generated PHP wrappers (under existing App\ PSR-4)
├── phel/
│   └── domain.phel
├── phel-config.php
└── composer.json
```

### `phel-config.php`

```php
<?php

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel'])
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['phel'])
        ->setNamespacePrefix('App\\PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/src/PhelGenerated'));
```

No `composer.json` changes needed; Symfony's default `App\ → src/` PSR-4 entry already covers `App\PhelGenerated\`.

### Call from a controller

Wrappers expose static methods, so call them directly; no DI wiring required.

```php
namespace App\Controller;

use App\PhelGenerated\Domain;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ReportController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(Domain::summarize($input));
    }
}
```

---

## Framework-less (or when `src/` is already PHP)

Same mechanics, no framework glue.

### `phel-config.php`

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject(mainNamespace: 'app\\main')
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel']);
```

### Call dynamically (no export step)

```php
<?php

require __DIR__ . '/vendor/autoload.php';

\Phel::bootstrap(__DIR__);

$greet = \Phel::getDefinition('app\\main', 'greet');
echo $greet('World');
```

Use this shape for scripts, cron jobs, or when you want to avoid a build step. For long-lived apps, prefer the exported-wrapper flavor: it caches definition lookups and gives IDEs something to autocomplete.

---

## Tips

- **Namespaces mirror directories.** `phel/pricing.phel` declares `(ns pricing)`; `phel/domain/cart.phel` declares `(ns domain\cart)`.
- **Hyphens become underscores in PHP.** `(ns my-lib)` exports as `PhelGenerated\MyLib` with camelCased method names (`apply-discount` → `applyDiscount`).
- **Cache and temp paths.** Phel writes to `sys_get_temp_dir()/phel/` by default. Override via `setCacheDir()` / `setTempDir()` if your host restricts that.
- **CI.** Run `vendor/bin/phel test` alongside `phpunit`. Add `vendor/bin/phel export` to the build step so generated wrappers match your Phel sources.
- **REPL against your project.** `vendor/bin/phel repl` boots with your `phel-config.php`, so `(require 'pricing)` works immediately.

## See also

- [PHP/Phel Interop](php-interop.md) — lower-level `php/` forms, type conversions, exceptions.
- [Quickstart](quickstart.md) — zero-to-running tutorial for greenfield projects.
