# Framework Integration Recipes

Drop Phel into an existing PHP project without touching `app/` or `src/`.

## Core idea

Put Phel sources in a dedicated dir (e.g. `phel/`). Export `{:export true}` functions to PHP wrappers under your framework's existing `App\` PSR-4 root, then load the Phel namespace once at app boot so Registry has the definitions.

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` → typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

Both require loading the Phel namespace once at startup (service provider, kernel event, entry script):

```php
\Phel::run(__DIR__, 'shop\\pricing');
```

`\Phel::run($projectRootDir, $namespace)` bootstraps the runtime and evaluates the namespace so `Registry` has its defs. Without it, wrapper methods and `\Phel::getDefinition()` return null.

Namespaces must have at least two segments (`shop\pricing`, not `pricing`) so the generated PHP namespace is well-formed.

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

`app/Providers/PhelServiceProvider.php` (load Phel namespaces once per process):

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class PhelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Phel::run(base_path(), 'shop\\pricing');
    }
}
```

Register it in `config/app.php` or via package discovery, then call the wrapper:

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

Keep wrappers in sync via a composer script:

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

Load Phel on kernel boot (`src/Kernel.php` or a `KernelEvents::REQUEST` listener):

```php
public function boot(): void
{
    parent::boot();

    \Phel::run($this->getProjectDir(), 'reports\\domain');
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

## Notes

- Namespace path must match directory: `phel/shop/pricing.phel` → `(ns shop\pricing)`. Single-segment ns (`pricing`) produces invalid PHP on export; use at least two segments.
- Hyphens become camelCase: `(ns my-lib\core)` → `App\PhelGenerated\MyLib\Core`; `apply-discount` → `applyDiscount`.
- `\Phel::run()` is the only public entry point for loading a Phel namespace from PHP. Skip it and wrapper methods or `\Phel::getDefinition()` return null.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
