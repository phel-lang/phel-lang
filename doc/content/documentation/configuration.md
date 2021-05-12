+++
title = "Configuration"
weight = 17
+++

Phel comes with some configuration options. They are stored in the `composer.json` file in the `extra` section.

## Structure

These are all Phel specific configuration options available.

```json
"extra": {
    "phel": {
        "loader": {
            "hello-world\\": "src/"
        },
        "loader-dev": {
            "hello-world\\tests\\": "tests/"
        },
        "tests": [
            "tests/"
        ],
        "export": {
            "directories": [
                "src/phel"
            ],
            "namespace-prefix": "PhelGenerated",
            "target-directory": "src/PhelGenerated"
        }
    }
}
```

## Options in detail

This chapter contains all configuration options explained in detail.


### `loader`

Autoload mapping for a Phel autoloader.

The `loader` configuration defines a mapping from namespaces to paths. The paths are relative to the package root. When autoloading a module like `hello-world\boot` a namespace prefix `hello-world` pointing to a directory `src/` means that the autoloader will look for a file named `src/boot.phel` and include it if present.

Namespace prefixes must end with a backslash (`\\`) to avoid conflicts between similar prefixes. For example `hello` would match modules in the `hello-world` namespace so the trailing backslashes solve the problem: `hello\\` and `hello-world\\` are distinct.

The `loader` references are all added whenever a package is updated or installed, to the Phel Runtime which can be found in the generated file `vendor/PhelRuntime.php`.


### `loader-dev`

This section allows us to define autoload rules for development purposes.

Modules needed to run the test suite should not be included in the main autoload rules to avoid polluting the autoloader in production and when other people use the package as a dependency. Therefore, it is a good idea to rely on a dedicated path for your unit tests and to add it within the `loader-dev` section.

The `loader-dev` configuration section is equivalent to the `loader` configuration section. Namespaces and paths are defined in the same way.

### `tests`

This configuration entry defines a list of folders where the test files of a project can be found.

### `export`

These configuration options are used for the Phel export command that is described in the [PHP Interop](/documentation/php-interop/#calling-phel-functions-from-php) chapter. Currently, the export command requires three options:

- `directories`: Defines a list of directories in which the export command should search for export functions.
- `namespace-prefix`: Defines a namespace prefix for all generated PHP classes.
- `target-directory`: Defines the directory where the generated PHP classes are stored.

## Phel Composer Plugin

Phel Runtime is configured automatically by the plugin. Whenever a package is updated or installed a file is generated in `vendor/PhelRuntime.php`. This file initializes the Phel Runtime according to the defined `loader` and `loader-dev` configuration options.

The generated Runtime can be loaded like this.

```php
// src/index.php
<?php

$rt = require __DIR__ .'/../vendor/PhelRuntime.php';

$rt->loadNs('hello-world\boot');
```

The source of [Phel's composer plugin](https://github.com/phel-lang/phel-composer-plugin) can be found in a separate repository.


## Manually initializing and configuring the Runtime

It is possible to manually initialize and configure the Runtime as shown in this example.

```php
// src/index.php
<?php

use Phel\Runtime\RuntimeSingleton;

require __DIR__ .'/../vendor/autoload.php';

$rt = RuntimeSingleton::initialize();
$rt->addPath('hello-world\\', [__DIR__]);

$rt->loadNs('phel\core');
$rt->loadNs('hello-world\boot');
```
