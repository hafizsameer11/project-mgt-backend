#!/bin/bash
set -e

echo "Starting Laravel application..."

# Clear Laravel caches
php artisan optimize:clear || true

# Cache for production
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

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
