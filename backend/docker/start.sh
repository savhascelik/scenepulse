#!/bin/sh

echo "Starting Nebius 3D Circuit & Edge AI Lab API..."

# Ensure SQLite file exists if DB_CONNECTION=sqlite
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
    chown -R www-data:www-data /var/www/html/database
fi

# Clear old caches before migration
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run database migrations automatically
echo "Running database migrations..."
php artisan migrate --force

# Seed initial RBAC roles, limits, AI models, and Judge demo data idempotently
echo "Seeding roles, AI models, and Judge demo workspace..."
php artisan db:seed --force

# Warm production caches
echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache

# Ensure storage symlink & permissions
php artisan storage:link || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "Starting Nginx + PHP-FPM via Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
