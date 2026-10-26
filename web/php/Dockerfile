# PHP-FPM image for the Guenaga website: Laravel on PHP 8.5.12 (issue #18).
# 8.5.12 fixes PHP2Shell (CVE-2026-109618, CVE-2026-109619). Bump tags and digests together:
#   docker buildx imagetools inspect <image>:<tag>
#
# The Alpine variant: Debian trixie's libxml2 is still based on 2.9.14 and has CVE-2026-6653
# (fixed upstream in 2.11.0, which Debian won't backport), while Alpine's is 2.13. It also comes
# without a compiler and is a fifth of the size. www-data is uid 82 here.
#
# Stages: base (PHP + extensions) -> dev (adds Composer; the "tools" service in compose.dev.yaml)
#         -> vendor (installs production dependencies) -> runtime (what web01 runs; no Composer).

FROM php:8.5.12-fpm-alpine3.24@sha256:4c2818074ec49f6abe46620f3965e0cb75bc3a5f2b417edd1aacaba6f091c40e AS base

# pdo_pgsql for PostgreSQL; gd to re-encode uploaded photos (and draw a self-hosted CAPTCHA,
# since php has no route to the internet). The compiler and -dev packages are removed afterwards
# and only the libraries the extensions link against are kept. apk upgrade takes Alpine's
# security fixes released since the base image was built, so every build (CI rebuilds weekly)
# has them.
RUN set -eux; \
    apk upgrade --no-cache; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS scanelf \
        postgresql-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql gd; \
    runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive "$(php -r 'echo ini_get("extension_dir");')" \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')"; \
    apk add --no-cache --virtual .php-extension-libs $runDeps; \
    apk del --no-network .build-deps

WORKDIR /var/www/app


FROM base AS dev

RUN apk add --no-cache unzip
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
