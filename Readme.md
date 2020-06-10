# The Phel Language

Phel is a function programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

[Read the documentation](https://phel-lang.org)

The rest of this Readme file documents on how to work with the compiler code.

## Community

Feel free to ask questions and join discussions on the [Phel Gitter channel](https://gitter.im/phel-lang/community).


## Development and contribution

### Your first try!

1. Clone/Fork the project and `cd` inside the repository
2. `docker-compose up`
3. `docker exec -ti -u dev phel_lang_php bash`
4. `composer install`
5. `composer test-all`

### Build the documentation

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

### Tests

Phel has two test suites: `test-compiler` & `test-core`:

```bash
composer test-compiler # it runs a PHPUnit test suite to test the compiler itself.
composer test-core     # it is a simple Phel script to test the core library.
composer test-all      # (both: compiler & core)
```

### Run on PHP 8 with JIT

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
