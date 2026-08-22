#!/bin/bash
set -e

cd /app

if [ ! -f .env ]; then
  echo "!! .env manquant dans le repo — copie depuis .env.example, adaptée au MySQL de la stack"
  cp .env.example .env
  # ADR-001 : le développement se fait sur MySQL, comme la production. L'exemple
  # du starter kit pointe sur SQLite, ce qui laissait le MySQL de la stack tourner
  # à vide — et masquait les bugs propres à MySQL (cf. IFO-006).
  # On ne touche qu'au .env créé ici : un .env existant reste tel quel.
  sed -i \
    -e 's/^DB_CONNECTION=sqlite$/DB_CONNECTION=mysql/' \
    -e 's/^# DB_HOST=127\.0\.0\.1$/DB_HOST=mysql/' \
    -e 's/^# DB_PORT=3306$/DB_PORT=3306/' \
    -e 's/^# DB_DATABASE=laravel$/DB_DATABASE=ifosup_display/' \
    -e 's/^# DB_USERNAME=root$/DB_USERNAME=ifosup/' \
    -e 's/^# DB_PASSWORD=$/DB_PASSWORD=secret/' \
    .env
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
