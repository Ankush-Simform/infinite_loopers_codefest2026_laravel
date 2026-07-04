#!/bin/sh

set -e

if [ ! -d vendor ]; then
    composer install
fi

chmod -R 775 storage bootstrap/cache || true

php artisan config:clear || true
php artisan cache:clear || true

exec "$@"