#!/bin/sh
set -eu

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
