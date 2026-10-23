# PHP-FPM image for the Guenaga website: Laravel on PHP 8.5.12 (issue #18).
# 8.5.12 fixes PHP2Shell (CVE-2026-109618, CVE-2026-109619). Bump tags and digests together:
#   docker buildx imagetools inspect <image>:<tag>
#
# Stages: base (PHP + extensions) -> dev (adds Composer; the "tools" service in compose.dev.yaml)
#         -> vendor (installs production dependencies) -> runtime (what web01 runs; no Composer).

FROM php:8.5.12-fpm@sha256:f665ad19c093eee54db3a03c89102d6828b1bbf4f327689e5947a8686c6585a1 AS base

# pdo_pgsql for PostgreSQL; gd to re-encode uploaded photos (and draw a self-hosted CAPTCHA,
# since php has no route to the internet). The -dev packages are purged afterwards and only
# the runtime libraries the extensions link against are kept.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql gd; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark; \
    ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/app


FROM base AS dev

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10.3@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer


FROM dev AS vendor

# Dependencies first, so editing app code does not reinstall them
COPY laravel/composer.json laravel/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-autoloader --no-scripts
COPY laravel/ ./
RUN composer dump-autoload --no-dev --optimize


FROM base AS runtime

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php/conf.d/zz-hardening.ini "$PHP_INI_DIR/conf.d/"
COPY php/fpm/zz-pool.conf /usr/local/etc/php-fpm.d/

# Code and vendor/ are owned by root, so PHP can read them but never change them.
# Only storage/app (the uploads volume, outside public/) belongs to www-data.
COPY --from=vendor /var/www/app /var/www/app
RUN chown -R www-data:www-data /var/www/app/storage/app

USER www-data
