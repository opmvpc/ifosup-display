# syntax=docker/dockerfile:1

# --- Stage 1 : Build (dépendances PHP + assets Vite) ---
FROM dunglas/frankenphp:php8.4-bookworm AS builder

# Node.js 22 + pnpm (le projet est verrouillé par pnpm-lock.yaml)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g pnpm@10 && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_mysql bcmath gd zip intl pcntl opcache

WORKDIR /app
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Dépendances PHP (lockfile inclus => build reproductible)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Dépendances JS
COPY package.json pnpm-lock.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

# Code applicatif
COPY . .

# artisan doit pouvoir booter pendant `pnpm build` (plugin Wayfinder).
# On fournit un .env neutre + une clé jetable, jamais utilisés au runtime.
RUN cp .env.example .env && \
    composer dump-autoload --optimize --no-dev && \
    php artisan key:generate --force && \
    pnpm build && \
    rm -f .env

# --- Stage 2 : Image finale ---
FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions pdo_mysql bcmath gd zip intl pcntl opcache

WORKDIR /app

# Code + vendor + assets compilés (sans node_modules ni .env)
COPY --from=builder /app /app
RUN rm -rf /app/node_modules

RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

COPY Caddyfile /Caddyfile
COPY docker-entrypoint.sh /start-container.sh
RUN chmod +x /start-container.sh

ENV PORT=80
ENV SERVER_NAME=:80
EXPOSE 80

# L'image FrankenPHP de base teste l'API d'admin de Caddy sur le port 2019, que le
# Caddyfile du projet désactive (`admin off`) : ce contrôle échouait donc en
# permanence et laissait le container « unhealthy », ce qu'un orchestrateur peut
# interpréter comme un déploiement raté. On interroge l'application à la place.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/" -o /dev/null || exit 1

ENTRYPOINT ["/start-container.sh"]
