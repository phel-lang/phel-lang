# Framework Integration Recipes

Drop Phel into an existing PHP project without touching `app/` or `src/`.

## Core idea

Put Phel sources in a dedicated dir (e.g. `phel/`). Generate PHP wrappers under the framework's existing `App\` PSR-4 root so no composer changes are needed.

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` → typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::bootstrap($root)` + `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

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

Controller:

```php
use App\PhelGenerated\Pricing;

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

Auto-regenerate wrappers on `composer install`:

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

Controller:

```php
use App\PhelGenerated\Domain;
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

Call without export:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

\Phel::bootstrap(__DIR__);

$greet = \Phel::getDefinition('app\\main', 'greet');
echo $greet('World');
```

---

## Notes

- `phel/pricing.phel` → `(ns pricing)`; `phel/domain/cart.phel` → `(ns domain\cart)`. Namespace must match path.
- Hyphens become camelCase: `(ns my-lib)` → `PhelGenerated\MyLib`; `apply-discount` → `applyDiscount`.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
