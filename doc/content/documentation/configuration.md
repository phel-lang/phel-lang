+++
title = "Configuration"
weight = 17
+++

Phel comes with some configuration options:
1. To define your custom namespaces inside your `composer.json` in the `extra` section.
2. All other configuration options will be saved inside the `config/` directory at the root level of your project.

## Structure

1. Composer loader options:

```json
"extra": {
    "phel": {
        "loader": {
            "hello-world\\": "src/"
        },
        "loader-dev": {
            "hello-world\\tests\\": "tests/"
        }
    }
}
```

2. Config options:
```php
use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

return [
    CommandConfig::DEFAULT_TEST_DIRECTORIES => ['tests'],
    InteropConfig::EXPORT_DIRECTORIES => ['src/modules/'],
    InteropConfig::EXPORT_NAMESPACE_PREFIX => 'PhelGenerated',
    InteropConfig::EXPORT_TARGET_DIRECTORY => 'src/PhelGenerated',
];
```

## Composer options in detail

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

## Config options in detail

### `tests`

This configuration entry defines a list of folders where the test files of a project can be found (`CommandConfig::DEFAULT_TEST_DIRECTORIES`).

### `export`

These configuration options are used for the Phel export command that is described in the [PHP Interop](/documentation/php-interop/#calling-phel-functions-from-php) chapter. Currently, the export command requires three options:

- `directories`: Defines a list of directories in which the export command should search for export functions (`InteropConfig::EXPORT_DIRECTORIES`).
- `namespace-prefix`: Defines a namespace prefix for all generated PHP classes (`InteropConfig::EXPORT_NAMESPACE_PREFIX`).
- `target-directory`: Defines the directory where the generated PHP classes are stored (`InteropConfig::EXPORT_TARGET_DIRECTORY`).

## Phel Composer Plugin

Phel Runtime is configured automatically by the plugin. Whenever a package is updated or installed a file is generated in `vendor/PhelRuntime.php`.
This file initializes the Phel Runtime according to the defined `loader` and `loader-dev` configuration options.

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
