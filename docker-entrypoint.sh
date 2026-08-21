#!/bin/bash

set -e

# S'assurer que les dossiers montés en volume sont accessibles à PHP
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Attendre que la base soit joignable (utile en compose / Coolify au 1er boot)
if [ "${DB_CONNECTION:-sqlite}" != "sqlite" ]; then
  echo "Waiting for database ${DB_HOST}:${DB_PORT} ..."
  for i in $(seq 1 60); do
    if php artisan db:show --quiet > /dev/null 2>&1; then
      echo "Database is up."
      break
    fi
    if [ "$i" = "60" ]; then
      echo "Database unreachable after 60 attempts, aborting." >&2
      exit 1
    fi
    sleep 2
  done
fi

echo "Running migrations ..."
php artisan migrate --force

# Crée l'admin depuis ADMIN_EMAIL / ADMIN_PASSWORD si la base est vide
php artisan app:create-admin-user

php artisan storage:link --force || true

php artisan optimize:clear
php artisan optimize

echo "Starting Laravel server ..."

# Start the FrankenPHP server
exec frankenphp run --config /Caddyfile --adapter caddyfile 2>&1
