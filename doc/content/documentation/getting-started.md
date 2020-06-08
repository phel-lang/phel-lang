+++
title = "Getting started"
weight = 1
+++

## Requirements

Phel requires PHP 7.4 or higher and [Composer](https://getcomposer.org/).

## Initialize a new project using Composer

The easiest way to get started is by setting up a new Composer project. First create a new directory and initialize a new Composer project

```bash
mkdir hello-world
cd hello-world
composer init
```

Composer will ask a bunch of questions that can be answered as in the following example:

```
Welcome to the Composer config generator

This command will guide you through creating your composer.json config.

Package name (<vendor>/<name>) [jens/phel]: phel/hello-world
Description []:
Author [Your Name <your.name@domain.com>, n to skip]:
Minimum Stability []:
Package Type (e.g. library, project, metapackage, composer-plugin) []: project
License []:

Define your dependencies.

Would you like to define your dependencies (require) interactively [yes]? no
Would you like to define your dev dependencies (require-dev) interactively [yes]? no

{
    "name": "phel/hello-world",
    "type": "project",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.name@domain.com"
        }
    ],
    "require": {}
}

Do you confirm generation [yes]? yes
```

Next we can require Phel as dependecy of our project.

```bash
# Require and install Phel
composer require phel/phel:dev-master
```

The final `composer.json` file should look like this:

```json
{
    "name": "phel/hello-world",
    "type": "project",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.name@domain.com"
        }
    ],
    "require": {
        "phel/phel": "dev-master"
    }
}
```

## Launch the REPL

You should now be able to run a REPL by executing the `./vendor/bin/phel repl` command.

## Start the Phel Runtime

After setting up the project we can now write some code. First we create new directory `src` and add two files in this directory.

```bash
mkdir src
```

The file `boot.phel` contains our actual code of the project. It just defines the namespace and prints "Hello World".

```phel
# in src/boot.phel
(ns hello-world\boot)

(print "<h1>Hello World</h1>")
```

The file `index.php` will be executed by the PHP Server. It initializes the Phel Runtime and loads Phel's core library and the `boot.phel` file, we described above.

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

The PHP Server can now be started.

```bash
# Start server
php -S localhost:8000 ./src/index.php
```

In the browser, the URL `http://localhost:8000` will now print "Hello World".

## Editor support

Phel comes with a basic editor support for VSCode. Please checkout the [plugin's README file](https://github.com/jenshaase/phel-lang/tree/master/editor-support/vscode) for more information.