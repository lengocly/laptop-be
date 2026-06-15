#!/bin/sh
set -e

if [ ! -f .env ]; then
  cp .env.docker .env
fi

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan config:clear
php artisan migrate --force --seed
php artisan storage:link --force || true

exec "$@"