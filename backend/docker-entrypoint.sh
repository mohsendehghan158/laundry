#!/bin/sh
# Production container start: cache configuration, optionally migrate, then
# run the given command (the web server, a queue worker or the scheduler).
set -e

php artisan optimize

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan laundry:sync-permissions
fi

exec "$@"
