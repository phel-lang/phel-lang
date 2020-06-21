+++
title = "Configuration"
weight = 15
+++

Phel comes with some configuration options. They are stored in the `composer.json` file under `extra`.

## Structure

These are all Phel specific configuration options available.

```json
"extra": {
    "phel": {
        "loader": {
            "hello-world\\": "src/"
        },
        "loader-dev": {
            "hello-world\\": "tests/"
        }
    }
}
```

Until Phel is officialy released, setting `minimum-stability` to `dev` in `composer.json` is required.

```json
"minimum-stability": "dev"
```

## Options

This chapter contains all configuration options explained in detail.


### `loader`

Autoload mapping for a Phel autoloader.

Under the `loader` key you define a mapping from namespaces to paths, relative to the package root. When autoloading a module like `hello-world\boot` a namespace prefix `hello-world` pointing to a directory `src/` means that the autoloader will look for a file named `src/boot.phel` and include it if present.

Namespace prefixes must end in `\\` to avoid conflicts between similar prefixes. For example `hello` would match modules in the `hello-world` namespace so the trailing backslashes solve the problem: `hello\\` and `hello-world\\` are distinct.

The `loader` references are all added, during install/update, to the Phel runtime which may be found in the generated file `vendor/PhelRuntime.php`.


### `loader-dev`

This section allows to define autoload rules for development purposes.

Modules needed to run the test suite should not be included in the main autoload rules to avoid polluting the autoloader in production and when other people use your package as a dependency.

Therefore, it is a good idea to rely on a dedicated path for your unit tests and to add it within the `loader-dev` section.


## Phel Composer Plugin

Phel runtime is configured automatically by the plugin. On package install/update it generates a file `vendor/PhelRuntime.php` which initializes the Phel runtime and adds package namespaces defined in `loader` and `loader-dev`.

Example on how to load the generated runtime.

```php
// src/index.php
<?php

$rt = require __DIR__ .'/../vendor/PhelRuntime.php';

$rt->loadNs('hello-world\boot');
```

View source code of the [Composer plugin for the Phel language](https://github.com/jenshaase/phel-composer-plugin).


## Manually initializing and configuring the runtime

It is possible to manually initialize and configure the runtime as shown in this example.

```php
// src/index.php
<?php

use Phel\Runtime;

require __DIR__ .'/../vendor/autoload.php';

$rt = Runtime::initialize();
$rt->addPath('hello-world\\', [__DIR__]);

$rt->loadNs('phel\core');
$rt->loadNs('hello-world\boot');
```