#!/bin/sh
set -e

# 1. Ensure storage and bootstrap cache directories exist and are writable
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache 2>/dev/null || true

chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 2. Optimize and cache Laravel configuration, routes, and views for production
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "uat" ]; then
    echo "Caching configuration, routes, and views for environment: $APP_ENV..."
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "=========================================================="
echo " Starting E-Budget PHP 8.3 / Laravel 13 on FrankenPHP"
echo " Production Domain: https://e-budget.thonglorpet.com/"
echo " Portainer Port Mapping: 5895 -> 8000"
echo " Container Name: e-budget-web-prd"
echo "=========================================================="

if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp run --config /etc/caddy/Caddyfile
elif command -v php-fpm >/dev/null 2>&1; then
    exec php-fpm
else
    exec php artisan serve --host=0.0.0.0 --port=8000
fi
