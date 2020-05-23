# The Phel Language

### Run on PHP 8 with JIT

Pull the docker image

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