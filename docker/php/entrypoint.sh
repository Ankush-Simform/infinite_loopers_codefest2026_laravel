#!/bin/sh

set -e

if [ ! -d vendor ]; then
    composer install
fi

# Create storage directories
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/public

# Create bootstrap/cache
mkdir -p bootstrap/cache

# Assign ownership to www-data
chown -R www-data:www-data storage bootstrap/cache || true

# Chmod storage and bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# Clear config and cache
php artisan config:clear || true
php artisan cache:clear || true

exec "$@"