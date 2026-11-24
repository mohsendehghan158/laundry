#!/bin/sh
set -e

# Configuration comes from environment variables; cache it once per start.
php artisan config:cache
php artisan route:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    # Bring roles and permissions in line with the code after an upgrade.
    php artisan laundry:sync-permissions
fi

exec "$@"
