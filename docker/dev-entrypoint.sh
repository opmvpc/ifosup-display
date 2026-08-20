#!/bin/bash
set -e

cd /app

if [ ! -f .env ]; then
  echo "!! .env manquant dans le repo — copie depuis .env.example"
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  echo ">> composer install"
  composer install --no-interaction
fi

if [ ! -d node_modules ]; then
  echo ">> pnpm install"
  pnpm install --frozen-lockfile
fi

grep -q '^APP_KEY=base64:' .env || php artisan key:generate

case "$1" in
  serve)
    echo ">> attente de la base ${DB_HOST}:${DB_PORT}"
    for i in $(seq 1 60); do
      php artisan db:show --quiet > /dev/null 2>&1 && break
      sleep 2
    done
    php artisan migrate --force
    php artisan app:create-admin-user
    php artisan storage:link --force || true
    php artisan optimize:clear
    # wayfinder génère resources/js/{actions,routes,wayfinder} consommés par Vite
    php artisan wayfinder:generate --with-form || true
    exec php artisan serve --host=0.0.0.0 --port=8000
    ;;
  vite)
    exec pnpm dev --host 0.0.0.0 --port 5173
    ;;
  *)
    exec "$@"
    ;;
esac
