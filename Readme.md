# The Phel Language

Phel is a function programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

## Documentation

The documentation for Phel can be found on Phel's website

[Read the documentation](https://phel-lang.org)

## Community

Feel free to ask questions and join discussions on the [Phel Gitter channel](https://gitter.im/phel-lang/community).

## Contribute

You are more than welcome to contribute to Phel. You can do so by either:

* reporting bugs
* contributing changes
* enrich the documentation

## Development

### Requirements

Phel requires PHP 7.4 or higher and Composer.

You can either install the dependencies by yourself or use the provided docker container:

```bash
docker-compose up
docker exec -ti -u dev phel_lang_php bash
composer install
composer test-all
```

### Testing

Phel has two test suites. The first test suite runs PHPUnit to test the compiler itself. The second test suite runs tests against Phel's core library.

```bash
composer test-compiler # test the compiler.
composer test-core     # test core library
composer test-all      # runs both script after each other
```

### Build the documentation

The documentation is build with [Zola](https://www.getzola.org/). To build and serve the documentation on a local machine, run:

```bash
cd doc
zola serve
```

To build the documentation to publish it on the server, run:

```bash
cd doc
zola build
```

### Run on PHP 8 with JIT

The JIT compiler in PHP 8 provides more speed for the Phel compiler. To compare the runtime on PHP 7.4 vs PHP 8 the following commands can be use.

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
