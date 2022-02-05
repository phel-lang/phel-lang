FROM php:8.1-cli

# Install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Install zip extension
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-install zip

# Create project
RUN mkdir /usr/src/repl
RUN mkdir /usr/src/repl/src
RUN mkdir /usr/src/repl/tests
COPY ./composer.json /usr/src/repl
RUN cd /usr/src/repl && composer update
WORKDIR /usr/src/repl

# Execute repl
CMD ["./vendor/bin/phel", "repl"]
