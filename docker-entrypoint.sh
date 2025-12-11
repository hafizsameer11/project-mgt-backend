#!/bin/sh

set -e

echo "Running Laravel setup..."

# Clear and cache config
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Cache configuration for better performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Laravel setup completed!"

# Execute the main command
exec "$@"

