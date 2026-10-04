FROM php:8.5-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# `unzip` lets composer unpack dist archives. It replaces ext-zip, which needed
# libzip-dev plus a compile step at build and libzip at runtime.
RUN apk add --no-cache unzip

# The release to install. The publish workflow passes the tag's version; a
# local build without it gets `main`.
ARG PHEL_VERSION=dev-main

WORKDIR /usr/src/repl
COPY ./composer.json ./
RUN mkdir -p src tests \
  && composer require --no-update --no-interaction "phel-lang/phel-lang:${PHEL_VERSION}" \
  && composer update --no-dev --prefer-dist --no-interaction --optimize-autoloader \
  && composer clear-cache

CMD ["./vendor/bin/phel", "repl"]
