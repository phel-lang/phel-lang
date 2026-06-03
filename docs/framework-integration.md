# Framework Integration Recipes

Add Phel to a PHP project without touching `app/` or `src/`.

## Core idea

1. Keep Phel sources under `phel/`.
2. Mark public functions with `{:export true}`.
3. Create one main namespace (`app.main`) that `:require`s every feature namespace. Loading it registers all exported functions at once.
4. Export PHP wrappers under your framework's `App\` PSR-4 root via `phel export`.
5. Prod: `phel build` at deploy, `require 'build/app/main.php'` at boot. Dev: `\Phel::run($root, 'app.main')` compiles on first call.

Namespaces need at least two segments (`shop.pricing`, not `pricing`); a single-segment ns exports invalid PHP.

Two ways to call Phel from PHP:

| Flavor | How | When |
|--------|-----|------|
| Exported wrappers | `{:export true}` + `vendor/bin/phel export` to typed PHP class | Production, IDE autocomplete |
| Dynamic lookup | `\Phel::getDefinition($ns, $name)(...)` | Scripts, prototyping |

Two load modes, same provider/kernel hook:

| Mode | What | Per-request cost |
|------|------|------------------|
| Prod (AOT) | `require 'build/app/main.php'`, precompiled | Zero compile, one `require` |
| Dev (JIT) | `\Phel::run($root, 'app.main')` | Gacela bootstrap + compile on first call |

Install: `composer require phel-lang/phel-lang`

Build artifacts on deploy (Composer runs both):

```json
"scripts": {
    "post-install-cmd": ["./vendor/bin/phel export", "./vendor/bin/phel build"],
    "post-update-cmd": ["./vendor/bin/phel export", "./vendor/bin/phel build"]
}
```

---

## Main namespace pattern

```
phel/
├── app/main.phel          ; main namespace, lists every feature ns
├── shop/pricing.phel
├── reports/daily.phel
└── auth/tokens.phel
```

`phel/app/main.phel`:

```phel
(ns app.main
  (:require shop.pricing)
  (:require reports.daily)
  (:require auth.tokens))
```

Loading `app.main` (via `require` or `\Phel::run()`) registers every exported function across all three namespaces. The build walks requires transitively, so `build/app/main.php` `require_once`s every dependency. Any controller can then call any wrapper:

```php
App\PhelGenerated\Shop\Pricing::applyDiscount(...)
App\PhelGenerated\Reports\Daily::summary(...)
App\PhelGenerated\Auth\Tokens::makeToken(...)
```

New feature: add the `.phel` file, add one `:require` in `app/main.phel`, rerun `phel export` + `phel build`.

---

## Laravel

`phel-config.php`:

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject()
    ->withSrcDirs(['phel'])
    ->withTestDirs(['tests-phel'])
    ->withBuildDestDir('build')
    ->withExportFromDirectories(['phel'])
    ->withExportNamespacePrefix('App\\PhelGenerated')
    ->withExportTargetDirectory(__DIR__ . '/app/PhelGenerated');
```

`app/Providers/PhelServiceProvider.php` (loads the main ns once, all wrappers ready):

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

        $built = base_path('build/app/main.php');

        if (is_file($built)) {
            require $built;
        } else {
            \Phel::run(base_path(), 'app.main');
        }

        self::$loaded = true;
    }
}
```

Controller:

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

`phel-config.php` (only the export target differs from Laravel):

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject()
    ->withSrcDirs(['phel'])
    ->withTestDirs(['tests/phel'])
    ->withBuildDestDir('build')
    ->withExportFromDirectories(['phel'])
    ->withExportNamespacePrefix('App\\PhelGenerated')
    ->withExportTargetDirectory(__DIR__ . '/src/PhelGenerated');
```

The default `App\ → src/` PSR-4 mapping covers `App\PhelGenerated\`.

`src/Kernel.php`:

```php
private static bool $phelLoaded = false;

public function boot(): void
{
    parent::boot();

    if (self::$phelLoaded) {
        return;
    }

    $built = $this->getProjectDir() . '/build/app/main.php';

    if (is_file($built)) {
        require $built;
    } else {
        \Phel::run($this->getProjectDir(), 'app.main');
    }

    self::$phelLoaded = true;
}
```

Controllers use any wrapper (`App\PhelGenerated\Reports\Daily`, etc.), all registered by the main load.

---

## Framework-less / existing `src/`

`phel-config.php`:

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject(mainNamespace: 'app.main')
    ->withSrcDirs(['phel'])
    ->withTestDirs(['tests/phel'])
    ->withBuildDestDir('build');
```

Entry script:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$built = __DIR__ . '/build/app/main.php';

if (is_file($built)) {
    require $built;
} else {
    \Phel::run(__DIR__, 'app.main');
}

// Call anything
$greet = \Phel::getDefinition('app.main', 'greet');
echo $greet('World') . "\n";
```

---

## Persistence: maps, not entities

Phel models data as immutable values, not mutable identity-tracked objects. An ORM entity (Doctrine, Eloquent) is the opposite: a mutable object the framework hydrates and dirty-tracks. Trying to make a Phel struct *be* an ORM entity fights the language. The functional path keeps rows as plain maps and pushes the database write to the edge.

Two small libraries cover it:

- [phel-sql](https://github.com/phel-lang/phel-sql) — HoneySQL-style. Map in, `[sql params]` out. No driver.
- [phel-pdo](https://github.com/phel-lang/phel-pdo) — runs `[sql params]`, returns rows as maps.

```phel
(ns shop.catalog
  (:require phel.sql :as sql)
  (:require phel.pdo :as pdo))

(defn find-product [conn id]
  (let [[query params] (sql/format {:select [:id :name :price]
                                    :from   [:products]
                                    :where  [:= :id id]})]
    (-> (pdo/prepare conn query)
        (pdo/execute params)
        (pdo/fetch))))                       ; => {:id 1 :name "Keyboard" :price 49.9}

(defn apply-discount [product pct]           ; pure: no DB, no mutation
  (update product :price (fn [p] (* p (- 1 pct)))))
```

For a runnable, dependency-free version of this pattern (raw PDO + SQLite), see [`docs/examples/13_database-crud.phel`](examples/13_database-crud.phel).

### Reuse the framework connection

Don't open a second connection. phel-sql is driver-agnostic, so its `[sql params]` feeds straight into the connection your framework already configured — e.g. Doctrine DBAL in Symfony:

```php
// Symfony service, $conn injected (Doctrine\DBAL\Connection)
[$sql, $params] = Catalog::buildProductQuery($id);   // exported Phel fn calling sql/format
$rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();
```

phel-pdo can also wrap an existing PDO handle so its map-returning helpers run on the host's pooled connection.

### Controllers, transactions, migrations

- **Routes/commands:** an exported `defn` can carry `^{:php/attr [[:Symfony.Component.Routing.Attribute/Route "/products/{id}"]]}`, so `phel export` emits the `#[Route]` on the generated wrapper — no hand-written controller shim.
- **Transactions:** wrap writes with phel-pdo's transaction helpers; keep the pure work outside the transaction and only the effect inside.
- **Migrations:** stay in PHP-land — reuse `doctrine/migrations` or your framework's tool. Phel does not need its own.

## Notes

- Namespace path matches directory: `phel/shop/pricing.phel` to `(ns shop.pricing)`.
- Hyphens become camelCase: `(ns my-lib.core)` to `App\PhelGenerated\MyLib\Core`; `apply-discount` to `applyDiscount`.
- Prod path (`require build/app/main.php`): self-contained, no Gacela bootstrap, no compiler. Just `\Phel::addDefinition()` calls.
- Dev path (`\Phel::run()`) boots Gacela and compiles to temp files on first call. Guard with a static flag; never call from Laravel `register()` or a per-request hot path.
- `withBuildDestDir()` is relative to the project root.
- Commit `build/` in the deploy artifact, or run `phel build` in CI. Skip committing in dev so `is_file()` is false and `\Phel::run()` kicks in.
- Add `vendor/bin/phel test` to CI alongside `phpunit`.

## See also

- [PHP/Phel Interop](php-interop.md)
- [Quickstart](quickstart.md)
- [Database CRUD example](examples/13_database-crud.phel) — runnable maps-not-entities pattern
- [phel-sql](https://github.com/phel-lang/phel-sql) and [phel-pdo](https://github.com/phel-lang/phel-pdo) — data-driven SQL + PDO wrapper

---

📖 **Full guide:** [Build a Web App on phel-lang.org](https://phel-lang.org/documentation/guides/build-a-web-app/)
