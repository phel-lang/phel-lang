# Framework Integration Recipes

Add Phel to an existing PHP project without touching `app/` or `src/`.

## Core idea

Keep Phel sources in a dedicated dir (e.g. `phel/`). Export `{:export true}` functions as typed PHP wrappers under your framework's `App\` PSR-4 root. Load the namespace once at boot.

Two load modes, both via the same provider/kernel hook:

| Mode | What | When |
|------|------|------|
| Prod (AOT) | `require 'build/<ns>.php'` — precompiled, zero runtime compile | Production, CI, every deploy |
| Dev (JIT) | `\Phel::run($root, $ns)` — compiles on first call | Local development |

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` → typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

Namespaces need at least two segments (`shop\pricing`, not `pricing`).

Install: `composer require phel-lang/phel-lang`

Build artifacts on every deploy (Composer does it for you):

```json
"scripts": {
    "post-install-cmd": ["phel export", "phel build"],
    "post-update-cmd": ["phel export", "phel build"]
}
```

---

## Laravel

`phel-config.php`:

```php
<?php

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests-phel'])
    ->setBuildConfig((new PhelBuildConfig())->setDestDir('build'))
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

        $built = base_path('build/shop/pricing.php');

        if (is_file($built)) {
            require $built;
        } else {
            \Phel::run(base_path(), 'shop\\pricing');
        }

        self::$loaded = true;
    }
}
```

Prod: `phel build` runs on deploy, `build/shop/pricing.php` is present → `require` wins, no runtime compile.
Dev: `build/` absent → `\Phel::run()` compiles on first request.

Controller:

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

---

## Symfony

`phel-config.php`:

```php
<?php

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return PhelConfig::forProject()
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel'])
    ->setBuildConfig((new PhelBuildConfig())->setDestDir('build'))
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['phel'])
        ->setNamespacePrefix('App\\PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/src/PhelGenerated'));
```

Default `App\ → src/` PSR-4 covers `App\PhelGenerated\`.

`src/Kernel.php`:

```php
private static bool $phelLoaded = false;

public function boot(): void
{
    parent::boot();

    if (self::$phelLoaded) {
        return;
    }

    $built = $this->getProjectDir() . '/build/reports/domain.php';

    if (is_file($built)) {
        require $built;
    } else {
        \Phel::run($this->getProjectDir(), 'reports\\domain');
    }

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

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return PhelConfig::forProject(mainNamespace: 'app\\main')
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel'])
    ->setBuildConfig((new PhelBuildConfig())->setDestDir('build'));
```

`phel/app/main.phel`:

```phel
(ns app\main)

(defn greet [name]
  (str "Hello, " name "!"))
```

Entry script (same prod/dev switch):

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$built = __DIR__ . '/build/app/main.php';

if (is_file($built)) {
    require $built;
} else {
    \Phel::run(__DIR__, 'app\\main');
}

$greet = \Phel::getDefinition('app\\main', 'greet');
echo $greet('World') . "\n";
```

---

## Notes

- Namespace path matches directory: `phel/shop/pricing.phel` → `(ns shop\pricing)`. Single-segment ns exports invalid PHP; use at least two segments.
- Hyphens become camelCase: `(ns my-lib\core)` → `App\PhelGenerated\MyLib\Core`; `apply-discount` → `applyDiscount`.
- Prod path (`require build/...`) is self-contained: no Gacela bootstrap, no compiler, just `\Phel::addDefinition()` calls. One `require`, zero overhead per request.
- Dev path (`\Phel::run()`) boots Gacela and compiles to temp files on first call. Guard with a static flag — never call from Laravel `register()` or per-request hot paths.
- `setBuildConfig()` dest dir is relative to the project root.
- Commit `build/` in the deploy artifact or run `phel build` in CI. Skip committing in dev so `is_file()` stays false and `\Phel::run()` kicks in.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
