# The Phel Language

Phel is a function programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

[Read the documentation](https://phel-lang.com)

The rest of this Readme file documents on how to work with the compiler code.

## Build the documentation

The documentation is build with [Zola](https://www.getzola.org/). To build and serve the documentation on a local machine, run:

```
cd doc
zola serve
```

To build the documentation to publish it on the server, run:

```
cd doc
zola build
```

## Test

Phel has two test suites. The first test suite runs a PHPUnit test to test the compiler itself. The second test suite is a simple Phel script to test the core library.

```
composer phpunit
composer test
```

## Run on PHP 8 with JIT

The JIT compiler in PHP 8 provides more speed for the Phel compiler. To compare the runtime on PHP 7.4 vs PHP 8 the following command can be use.

First, pull the docker image

```
docker pull keinos/php8-jit:latest
```

Run the PHPUnit tests
```
sudo docker run --rm \
    -v $(pwd):/app \
    -w /app \
    keinos/php8-jit \
    ./vendor/bin/phpunit
```

Run the Phel test suite
```
sudo docker run --rm \
    -v $(pwd):/app \
    -w /app \
    keinos/php8-jit \
    php tests/phel/test-runner.php
```