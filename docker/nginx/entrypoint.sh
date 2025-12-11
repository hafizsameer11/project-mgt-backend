#!/bin/bash

# run php artisan optimize
php artisan optimize:clear

# start php-fpm
php-fpm &

# start nginx
nginx -g "daemon off;"
