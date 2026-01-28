FROM ghcr.io/roadrunner-server/roadrunner:2025.1.6 AS roadrunner
FROM php:8.5-cli-alpine

RUN apk add --no-cache linux-headers ${PHPIZE_DEPS} icu-dev \
    && pecl install pcov xdebug redis \
    && docker-php-ext-enable pcov xdebug redis \
    && apk del ${PHPIZE_DEPS}

RUN apk add --no-cache git unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql pcntl sockets intl bcmath

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV ROADRUNNER_MODE=production

ARG BUILD_DEV=0

COPY composer.json composer.lock* ./
RUN if [ "$BUILD_DEV" = "1" ]; then composer install --no-scripts --no-interaction --prefer-dist; else composer install --no-dev --no-scripts --no-interaction --prefer-dist; fi 2>/dev/null || true

COPY . .
RUN if [ "$BUILD_DEV" = "1" ]; then composer install --no-scripts --no-interaction --prefer-dist; else composer install --no-dev --no-scripts --no-interaction --prefer-dist; fi

EXPOSE 8080

CMD ["rr", "serve", "-c", ".rr.yaml"]
