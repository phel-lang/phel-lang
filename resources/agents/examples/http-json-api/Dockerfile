# Multi-stage build for a Phel HTTP app.
#
# Build stage: install dependencies and compile Phel -> PHP ahead of time.
# Runtime stage: ship only PHP, the built output, and the no-dev vendor tree,
# so the compiler never runs on a request.

# --- Build stage -----------------------------------------------------------
FROM php:8.4-cli AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && ./vendor/bin/phel build --report

# --- Runtime stage ---------------------------------------------------------
FROM php:8.4-cli AS runtime

# Opcache for the compiled output (immutable image: skip timestamp checks).
RUN docker-php-ext-install opcache
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app
COPY --from=build /app/vendor /app/vendor
COPY --from=build /app/out /app/out
COPY --from=build /app/public /app/public
COPY --from=build /app/src /app/src

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
