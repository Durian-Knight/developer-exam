#!/bin/sh
set -e

cd /var/www/backend

# Demo convenience: generate an app key if none was supplied.
# (API tokens are SHA-256 hashed and don't depend on APP_KEY.)
if [ -z "$APP_KEY" ]; then
  export APP_KEY="$(php artisan key:generate --show --no-interaction)"
fi

php artisan package:discover --ansi
php artisan migrate --force

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
  # Fails harmlessly on restarts because the demo emails already exist.
  php artisan db:seed --force || echo "Seed skipped (demo data already present)."
fi

if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
