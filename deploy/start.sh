#!/bin/sh
set -eu

: "${APP_KEY:?Configura APP_KEY en Render}"
: "${DB_URL:?Configura DB_URL con la conexion privada de Neon}"
PORT="${PORT:-10000}"
case "$PORT" in ''|*[!0-9]*) echo 'PORT debe ser numerico' >&2; exit 1;; esac
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan view:cache
php artisan storage:link

# Activar solo al conectar una base nueva destinada a esta aplicacion.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --class=PermisoSeeder --force
fi

exec apache2-foreground
