# syntax=docker/dockerfile:1
# Laravel API. Production runs on FrankenPHP (Caddy + PHP in one process);
# the same image also runs the queue worker and the scheduler (see compose files).
# Build context: ./backend

FROM dunglas/frankenphp:1-php8.4 AS base
RUN install-php-extensions pdo_pgsql redis intl pcntl opcache zip bcmath
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# --- Local development: the source is mounted at /app -------------------------
FROM base AS dev
ENV APP_ENV=local
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# --- Dependencies --------------------------------------------------------------
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- Production ----------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf "opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=192\nexpose_php=0\n" > "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/app-entrypoint
# Caddy inside FrankenPHP keeps its state here; the unprivileged user must own it.
RUN chown -R www-data:www-data /data /config
USER www-data
# An unprivileged port, so the server does not need to run as root.
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
  CMD php -r "exit(@file_get_contents('http://127.0.0.1:8080/up') === false ? 1 : 0);"
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public/"]
