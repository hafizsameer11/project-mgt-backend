#!/bin/bash
set -e

echo "Starting Laravel application..."

# Create storage directories if they don't exist
mkdir -p /var/www/html/storage/app/public/requirements || true
mkdir -p /var/www/html/storage/app/public/project-documents || true
mkdir -p /var/www/html/storage/app/public/uploads || true
mkdir -p /var/www/html/storage/app/public/projects || true
mkdir -p /var/www/html/storage/framework/cache || true
mkdir -p /var/www/html/storage/framework/sessions || true
mkdir -p /var/www/html/storage/framework/views || true
mkdir -p /var/www/html/storage/logs || true

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Create storage symlink if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link || true
fi

# Clear Laravel caches
php artisan optimize:clear || true

# Cache for production
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting services with supervisor..."

# Start supervisor (which manages both PHP-FPM and Nginx)
# Try different supervisor paths for different Alpine versions
if [ -f /usr/bin/supervisord ]; then
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
elif [ -f /usr/local/bin/supervisord ]; then
    exec /usr/local/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    # Fallback: start services manually if supervisor not found
    echo "Supervisor not found, starting services manually..."
    php-fpm -F &
    nginx -g "daemon off;"
fi
