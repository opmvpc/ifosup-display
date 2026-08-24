#!/bin/bash

set -e

# Démarrage en deux temps : root prépare les volumes (dont les fichiers écrits
# en root par d'anciennes versions de l'image), puis tout le reste — artisan
# comme le serveur — tourne en www-data. Une compromission de l'application ne
# donne ainsi plus root dans le conteneur. Le port 80 reste accessible grâce à
# la capacité net_bind_service posée sur le binaire frankenphp (Dockerfile).
if [ "$(id -u)" = "0" ]; then
  mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
           storage/logs storage/app/public bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache || true

  # Répertoires d'état de Caddy (XDG_CONFIG_HOME=/config, XDG_DATA_HOME=/data
  # dans l'image de base).
  mkdir -p /config /data
  chown -R www-data:www-data /config /data || true

  # Lien public/storage : /app/public appartient à root, donc `artisan
  # storage:link` échouait plus bas en www-data et son erreur était avalée par
  # `|| true` — tous les médias de slides répondaient 404. Le lien se pose ici,
  # tant qu'on est root.
  ln -sfn /app/storage/app/public /app/public/storage

  exec setpriv --reuid=www-data --regid=www-data --init-groups "$0" "$@"
fi

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

# Le lien public/storage est posé par la phase root ci-dessus : ici, en
# www-data, storage:link ne peut pas écrire dans /app/public.

php artisan optimize:clear
php artisan optimize

echo "Starting Laravel server ..."

# Start the FrankenPHP server
exec frankenphp run --config /Caddyfile --adapter caddyfile 2>&1
