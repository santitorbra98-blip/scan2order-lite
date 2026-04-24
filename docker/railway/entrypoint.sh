#!/bin/sh
set -eu

export PORT="${PORT:-8080}"

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf