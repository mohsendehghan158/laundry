# syntax=docker/dockerfile:1

# Production dependencies only. Platform checks run in the final image,
# which has the real PHP version and extensions.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# FrankenPHP: Caddy with PHP built in, serving /app/public.
FROM dunglas/frankenphp:1-php8.4-alpine
RUN install-php-extensions pdo_pgsql redis intl bcmath pcntl opcache zip
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-laundry.ini
COPY docker/entrypoint.sh /usr/local/bin/laundry-entrypoint
WORKDIR /app
COPY --from=vendor /app /app
RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Plain HTTP on 8000; TLS is terminated by the reverse proxy in front.
ENV SERVER_NAME=:8000 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s CMD wget -qO- http://127.0.0.1:8000/up > /dev/null || exit 1
ENTRYPOINT ["laundry-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
