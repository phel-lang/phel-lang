+++
title = "Getting started"
weight = 1
+++

## Requirements

Phel requires PHP 7.4 or higher and [Composer](https://getcomposer.org/).

## Quick start

To get started right away the [Scaffolding project on Github](https://github.com/phel-lang/phel-scaffolding) can be used.

```bash
git clone https://github.com/phel-lang/phel-scaffolding.git
composer install
# Start the REPL to try Phel
./vendor/bin/phel repl
```

More information can be found in the Readme of the project.

## Manually initialize a new project using Composer

The easiest way to get started is by setting up a new Composer project. First, create a new directory and initialize a new Composer project.

```bash
mkdir hello-world
cd hello-world
composer init
```

Composer will ask a bunch of questions that can be answered as in the following example.

```
Welcome to the Composer config generator

This command will guide you through creating your composer.json config.

Package name (<vendor>/<name>): phel-lang/hello-world
Description []:
Author [Your Name <your.name@domain.com>, n to skip]:
Minimum Stability []:
Package Type (e.g. library, project, metapackage, composer-plugin) []: project
License []:

Define your dependencies.

Would you like to define your dependencies (require) interactively [yes]? no
Would you like to define your dev dependencies (require-dev) interactively [yes]? no

{
    "name": "phel-lang/hello-world",
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

Next, require Phel as a dependency.

```bash
# Require and install Phel
composer require phel-lang/phel-lang
```

Then, create a new directory `src` with a file `boot.phel` inside this directory.

```bash
mkdir src
```

The file `boot.phel` contains the actual code of the project. It defines the namespace and prints "Hello, World!".

```phel
# in src/boot.phel
(ns hello-world\boot)

(println "Hello, World!")
```

For Phel to automatically resolve the project namespace and path, this code needs to be added to `composer.json` file.

```json
"extra": {
    "phel": {
        "loader": {
            "hello-world\\": "src/"
        }
    }
}
```

> Read the documentation for [Configuration](/documentation/configuration) to see all available configuration options for Phel.

The final `composer.json` file should look like this:

```json
{
    "name": "phel-lang/hello-world",
    "type": "project",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.name@domain.com"
        }
    ],
    "require": {
        "phel-lang/phel-lang": "^0.1"
    },
    "extra": {
        "phel": {
            "loader": {
                "hello-world\\": "src/"
            }
        }
    }
}
```


## Running the code

Before execute the code, make sure composer has created a autoload file. This can be done by executing following command

```
composer dump-autoload
```

There are two ways to run the code: from the command line and with a PHP Server.


### From the Command line

Code can be executed from the command line by calling the `vendor/bin/phel run` command, followed by the file path or namespace:

```bash
vendor/bin/phel run src/boot.phel
# or
vendor/bin/phel run hello-world\\boot
# or
vendor/bin/phel run "hello-world\boot"
```

The output will be:

```
Hello, World!
```


### With a PHP Server

The file `index.php` will be executed by the PHP Server. It initializes the Phel Runtime and loads the namespace from the `boot.phel` file described above, to start the application.

```php
// src/index.php
<?php

$rt = require __DIR__ .'/../vendor/PhelRuntime.php';

$rt->loadNs('hello-world\boot');
```

The PHP Server can now be started.

```bash
# Start server
php -S localhost:8000 ./src/index.php
```

In the browser, the URL `http://localhost:8000` will now print "Hello, World!".


## Launch the REPL

To try Phel you can run a REPL by executing the `./vendor/bin/phel repl` command.

> Read more about the [REPL](/documentation/repl) in its own chapter.

## Editor support

Phel comes with basic editor support for VSCode. Please check out the [plugin's README file](https://github.com/phel-lang/phel-vs-code-extension) for more information.
