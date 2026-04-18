# Framework Integration Recipes

Add Phel to an existing PHP project without touching `app/` or `src/`.

## Core idea

Keep Phel sources in a dedicated dir (e.g. `phel/`). Export `{:export true}` functions as PHP wrappers under your framework's `App\` PSR-4 root. Load the namespace once at boot.

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` → typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

Two ways to load definitions at runtime:

| Mode | How | Cost per request |
|------|-----|------------------|
| JIT (dev) | `\Phel::run($root, $ns)` at boot — compiles on first call | Bootstrap + compile (cached after first) |
| AOT (prod) | `vendor/bin/phel build` once, `require 'build/ns/name.php'` at boot | Zero compile — just `require` |

Load the namespace once at startup:

```php
\Phel::run(__DIR__, 'shop\\pricing');
```

`\Phel::run($projectRootDir, $namespace)` bootstraps the runtime and registers the defs. Without it, wrappers and `\Phel::getDefinition()` return null.

Namespaces need at least two segments (`shop\pricing`, not `pricing`).

Install: `composer require phel-lang/phel-lang`

---

## Laravel

`phel-config.php`:

```php
<?php

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests-phel'])
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['phel'])
        ->setNamespacePrefix('App\\PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/app/PhelGenerated'));
```

`phel/shop/pricing.phel`:

```phel
(ns shop\pricing)

(defn apply-discount
  {:export true}
  [price percent]
  (- price (* price (/ percent 100))))
```

Generate the wrapper:

```bash
vendor/bin/phel export
```

`app/Providers/PhelServiceProvider.php`:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class PhelServiceProvider extends ServiceProvider
{
    private static bool $loaded = false;

    public function boot(): void
    {
        if (self::$loaded) {
            return;
        }

        \Phel::run(base_path(), 'shop\\pricing');
        self::$loaded = true;
    }
}
```

Register it, then call the wrapper:

```php
use App\PhelGenerated\Shop\Pricing;

final class CheckoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $total = Pricing::applyDiscount(
            (float) $request->input('price'),
            (float) $request->input('percent'),
        );

        return response()->json(['total' => $total]);
    }
}
```

Keep wrappers in sync:

```json
"scripts": {
    "post-autoload-dump": ["phel export"]
}
```

---

## Symfony

`phel-config.php`:

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

Default `App\ → src/` PSR-4 covers `App\PhelGenerated\`.

Load on kernel boot (`src/Kernel.php`):

```php
private static bool $phelLoaded = false;

public function boot(): void
{
    parent::boot();

    if (self::$phelLoaded) {
        return;
    }

    \Phel::run($this->getProjectDir(), 'reports\\domain');
    self::$phelLoaded = true;
}
```

Controller:

```php
use App\PhelGenerated\Reports\Domain;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReportController
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse(Domain::summarize($request->toArray()));
    }
}
```

---

## Framework-less / existing `src/`

`phel-config.php`:

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject(mainNamespace: 'app\\main')
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel']);
```

`phel/app/main.phel`:

```phel
(ns app\main)

(defn greet [name]
  (str "Hello, " name "!"))
```

Call dynamically:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

\Phel::run(__DIR__, 'app\\main');

$greet = \Phel::getDefinition('app\\main', 'greet');
echo $greet('World') . "\n";
```

---

## Production: ahead-of-time build

`\Phel::run()` boots Gacela and compiles on first call. Fine for dev. In production, compile once at deploy and skip bootstrap entirely.

Add a build config:

```php
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setBuildConfig((new PhelBuildConfig())->setDestDir('build'))
    // ...export config as before
    ;
```

Build at deploy time:

```bash
vendor/bin/phel build
```

Output: `build/<ns-path>.php` — self-contained, registers defs via `\Phel::addDefinition()`, only depends on the `\Phel` class from Composer autoload.

**Laravel** — replace `\Phel::run(...)` in the provider:

```php
public function boot(): void
{
    if (self::$loaded) {
        return;
    }

    require base_path('build/shop/pricing.php');
    self::$loaded = true;
}
```

**Symfony** — same swap in `Kernel::boot()`:

```php
require $this->getProjectDir() . '/build/reports/domain.php';
```

Wire into Composer so the build runs on deploy:

```json
"scripts": {
    "post-install-cmd": ["phel build", "phel export"],
    "post-update-cmd": ["phel build", "phel export"]
}
```

Commit `build/` in the deploy artifact (or generate during CI). No runtime Phel compiler, no Gacela bootstrap — just `require`.

---

## Notes

- Namespace path matches directory: `phel/shop/pricing.phel` → `(ns shop\pricing)`. Single-segment ns exports invalid PHP; use at least two segments.
- Hyphens become camelCase: `(ns my-lib\core)` → `App\PhelGenerated\MyLib\Core`; `apply-discount` → `applyDiscount`.
- `\Phel::run()` is the only public entry to load a namespace. Skip it and wrappers return null.
- `\Phel::run()` bootstraps Gacela and compiles temp files. Call it once per process (guard with a static flag, or load once on Octane/long-running workers). Avoid calling it from `register()` or per-request hot paths.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
