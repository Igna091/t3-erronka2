# The shop's web server: Apache with PHP 8.5 (mod_php), serving only src/public over HTTPS.
# Build context: the ekoizpengehigarriak folder (compose.yaml).
# Bump the tag and the digest together:  docker buildx imagetools inspect <image>:<tag>
#
# Stages: base (PHP, its extensions, Apache and PHP settings) -> dev (adds Composer, PHPUnit and
#         PHPStan, for the tests and CI) -> runtime (what runs: the app, as www-data).

FROM php:8.5.12-apache-trixie@sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96 AS base

# PDO for MySQL, and GD to check and redraw uploaded pictures. The -dev packages are only for
# compiling: afterwards just the libraries the extensions load stay installed.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd pdo_mysql; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | awk -F: '!/^diversion / { print $1 }' | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

# PHP: the production defaults, then this app's hardening
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY web/php/zz-hardening.ini $PHP_INI_DIR/conf.d/

# Apache: only the shop's site, on unprivileged ports, with TLS and the security headers
COPY web/apache/ports.conf /etc/apache2/ports.conf
COPY web/apache/zz-hardening.conf /etc/apache2/conf-available/zz-hardening.conf
COPY web/apache/bdweb.conf /etc/apache2/sites-available/bdweb.conf
RUN a2enmod ssl headers rewrite \
    && a2dissite 000-default \
    && a2enconf zz-hardening \
    && a2ensite bdweb

# Where the HTTP port sends browsers; compose.yaml sets them
ENV SERVER_NAME=localhost \
    HTTPS_PORT=8443

FROM base AS dev
# Composer and the tools live in /opt/tools and work on code mounted at /work, and Composer and
# PHPStan start processes with proc_open: lift the runtime's limits, in this stage only
RUN printf 'open_basedir =\ndisable_functions =\nmemory_limit = 1G\n' > "$PHP_INI_DIR/conf.d/zzz-dev-tools.ini"
COPY --from=composer:2.10.3@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/tools
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist
ENV PATH=/opt/tools/vendor/bin:$PATH
WORKDIR /work

FROM base AS runtime
# The code belongs to root, so the web server can read it but never change it. Only the two
# upload folders are its own; compose.yaml keeps them in volumes.
COPY src/ /var/www/app/
RUN chown -R www-data:www-data /var/www/app/public/images /var/www/app/storage/perfiles
USER www-data
WORKDIR /var/www/app
EXPOSE 8080 8443
