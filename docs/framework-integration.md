# Framework Integration Recipes

Add Phel to an existing PHP project without touching `app/` or `src/`.

## Core idea

1. Keep Phel sources under `phel/`.
2. Mark public functions with `{:export true}`.
3. Create **one boot namespace** (`app\boot`) that `:require`s every feature namespace. Loading it registers all exported functions at once.
4. Export PHP wrappers under your framework's `App\` PSR-4 root via `phel export`.
5. Prod: build at deploy (`phel build`), `require 'build/app/boot.php'` at boot. Dev: `\Phel::run($root, 'app\\boot')` compiles on first call.

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` to typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

Two load modes, same provider/kernel hook:

| Mode | What | Per-request cost |
|------|------|------------------|
| Prod (AOT) | `require 'build/app/boot.php'`, precompiled | Zero compile, one `require` |
| Dev (JIT) | `\Phel::run($root, 'app\\boot')` | Gacela bootstrap + compile on first call |

Namespaces need at least two segments (`shop\pricing`, not `pricing`).

Install: `composer require phel-lang/phel-lang`

Build artifacts on deploy (Composer runs both):

```json
"scripts": {
    "post-install-cmd": ["phel export", "phel build"],
    "post-update-cmd": ["phel export", "phel build"]
}
```

---

## Boot namespace pattern

```
phel/
├── app/boot.phel          ; lists every feature ns
├── shop/pricing.phel
├── reports/daily.phel
└── auth/tokens.phel
```

`phel/app/boot.phel`:

```phel
(ns app\boot
  (:require shop\pricing)
  (:require reports\daily)
  (:require auth\tokens))
```

Loading `app\boot` (via `require` or `\Phel::run()`) registers **every** exported function across all three namespaces. Any controller can then call any wrapper:

```php
App\PhelGenerated\Shop\Pricing::applyDiscount(...)
App\PhelGenerated\Reports\Daily::summary(...)
App\PhelGenerated\Auth\Tokens::makeToken(...)
```

New Phel feature: add the file, add one `:require` in `app/boot.phel`, run `phel export` + `phel build`.

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

`app/Providers/PhelServiceProvider.php` (loads the boot ns once, all wrappers ready):

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

        $built = base_path('build/app/boot.php');

        if (is_file($built)) {
            require $built;
        } else {
            \Phel::run(base_path(), 'app\\boot');
        }

        self::$loaded = true;
    }
}
```

Controller (any wrapper works without further setup):

```php
use App\PhelGenerated\Shop\Pricing;
use App\PhelGenerated\Reports\Daily;

final class CheckoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $total = Pricing::applyDiscount(
            (float) $request->input('price'),
            (float) $request->input('percent'),
        );

        return response()->json([
            'total' => $total,
            'report' => Daily::summary((int) $request->input('day')),
        ]);
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

    $built = $this->getProjectDir() . '/build/app/boot.php';

    if (is_file($built)) {
        require $built;
    } else {
        \Phel::run($this->getProjectDir(), 'app\\boot');
    }

    self::$phelLoaded = true;
}
```

Controllers use any wrapper: `App\PhelGenerated\Reports\Daily`, `App\PhelGenerated\Shop\Pricing`, etc. All registered by the single boot load.

---

## Framework-less / existing `src/`

`phel-config.php`:

```php
<?php

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return PhelConfig::forProject(mainNamespace: 'app\\boot')
    ->setSrcDirs(['phel'])
    ->setTestDirs(['tests/phel'])
    ->setBuildConfig((new PhelBuildConfig())->setDestDir('build'));
```

Entry script:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$built = __DIR__ . '/build/app/boot.php';

if (is_file($built)) {
    require $built;
} else {
    \Phel::run(__DIR__, 'app\\boot');
}

// Call anything
$greet = \Phel::getDefinition('app\\main', 'greet');
echo $greet('World') . "\n";
```

---

## Notes

- **Boot namespace**: `phel/app/boot.phel` lists one `(:require other\ns)` per feature namespace. The build step walks those requires transitively, so `build/app/boot.php` `require_once`s every dependency. Loading it from the provider/kernel registers every `{:export true}` function in one shot. Controllers then call any wrapper without knowing which Phel files exist. New feature: create the `.phel` file, add one `:require` in `app/boot.phel`, rerun `phel export` + `phel build`.
- Namespace path matches directory: `phel/shop/pricing.phel` to `(ns shop\pricing)`. Single-segment ns exports invalid PHP; use at least two segments.
- Hyphens become camelCase: `(ns my-lib\core)` to `App\PhelGenerated\MyLib\Core`; `apply-discount` to `applyDiscount`.
- Prod path (`require build/app/boot.php`): self-contained, no Gacela bootstrap, no compiler, just `\Phel::addDefinition()` calls.
- Dev path (`\Phel::run()`) boots Gacela and compiles to temp files on first call. Guard with a static flag; never call from Laravel `register()` or per-request hot paths.
- `setBuildConfig()` dest dir is relative to the project root.
- Commit `build/` in the deploy artifact or run `phel build` in CI. Skip committing in dev so `is_file()` is false and `\Phel::run()` kicks in.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
