#!/bin/sh
set -eu

export PORT="${PORT:-8080}"
export CACHE_STORE="${CACHE_STORE:-file}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

cd /var/www/html

# Laravel returns HTTP 500 for every request if APP_KEY is missing.
if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  echo "APP_KEY was not set; generated ephemeral runtime key."
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache storage/app/public
chown -R www-data:www-data storage bootstrap/cache || true

if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  max_attempts="${MIGRATION_MAX_ATTEMPTS:-12}"
  attempt=1

  until php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "Migration failed after ${max_attempts} attempts"
      exit 1
    fi

    echo "Migration attempt ${attempt} failed, retrying..."
    attempt=$((attempt + 1))
    sleep 5
  done
fi

# Cache config/routes/views for maximum performance in production.
# Must run after migrations so DB-dependent config resolves correctly.
php artisan config:cache --quiet  || true
php artisan route:cache  --quiet  || true
php artisan view:cache   --quiet  || true
php artisan event:cache  --quiet  || true

exec /usr/bin/supervisord -c /etc/supervisord.conf