# Installation locale (Docker)

Prérequis : Docker Desktop démarré. Rien d'autre — ni PHP, ni Node, ni Composer
sur la machine hôte.

## Stack « prod-like » — l'image qui partira sur Coolify

```bash
docker compose up -d --build
```

- Application : <http://localhost:8080>
- MySQL exposé sur le port hôte `33061` (base `ifosup_display`, user `ifosup` / `secret`)
- Configuration : `.env.docker` (non versionné ; modèle dans `.env.docker.example`)

Au premier démarrage, le container migre la base et crée le compte administrateur
défini par `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

```bash
docker compose logs -f app          # suivre le démarrage
docker compose exec app php artisan tinker
docker compose down                 # arrêter
docker compose down -v              # arrêter ET effacer la base
```

Aucun bind mount : après une modification du code, il faut reconstruire
(`docker compose up -d --build`). C'est voulu — cette stack sert à vérifier le
déploiement, pas à développer.

## Stack de développement — hot-reload

```bash
docker compose -f docker-compose.dev.yml up --build
```

- Application : <http://localhost:8001>
- Serveur Vite : <http://localhost:5175> (Laravel s'y branche automatiquement)
- MySQL exposé sur le port hôte `33063`

Ces ports sont volontairement décalés des ports usuels (8000, 5173, 3306), souvent
occupés par une autre application en cours de développement. Ils restent surchargeables :

```bash
APP_HOST_PORT=9000 VITE_HOST_PORT=5180 DB_HOST_PORT=33070   docker compose -f docker-compose.dev.yml up -d
```
- Configuration : `.env` à la racine (non versionné)

Le code est monté en volume : `composer install` et `pnpm install` s'exécutent dans
le container au premier lancement et déposent `vendor/` et `node_modules/` sur le
disque, ce qui rend l'IDE utilisable.

```bash
docker compose -f docker-compose.dev.yml exec app php artisan test
docker compose -f docker-compose.dev.yml exec app composer lint
docker compose -f docker-compose.dev.yml exec app pnpm types:check
docker compose -f docker-compose.dev.yml exec app php artisan db:seed
```

## Jeu de données de démonstration

`DatabaseSeeder` n'appelle que `UserSeeder` ; les seeders métier (enseignants,
locaux, sections, cours, attributions) sont commentés.

**Ces seeders ne fonctionnent que dans la stack de développement.** Les factories
utilisent `fake()`, fourni par `fakerphp/faker` qui est en `require-dev` : l'image de
production est construite avec `--no-dev`, donc la fonction n'y existe pas et le
seeder échoue sur `Call to undefined function Database\Factoriesake()`.
C'est le comportement souhaité — des données de démonstration n'ont rien à faire en
production — mais il faut le savoir.

```bash
# Stack de développement uniquement
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=TeacherSeeder
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=RoomSeeder
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=GroupSeeder
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=CourseSeeder
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=AssignmentSeeder
```

Pour peupler la stack prod-like, passer par du SQL (voir le journal du 2026-08-18)
ou saisir les données depuis le backoffice.

## Ports occupés

La stack de développement se surcharge par variables (`APP_HOST_PORT`, `VITE_HOST_PORT`,
`DB_HOST_PORT`). Pour la stack prod-like, modifier la partie gauche du mapping `ports:`
dans `docker-compose.yml`.

**Piège spécifique à Windows.** `localhost` y résout d'abord en IPv6 (`::1`). Un serveur
tiers écoutant sur `[::1]:5173` — typiquement le Vite d'un autre projet — capte alors les
requêtes du navigateur avant le conteneur, qui écoute pourtant bien sur `0.0.0.0:5173`.
Les symptômes sont déroutants : la page se charge, mais les composants Vue affichés
appartiennent à l'autre projet. `netstat -ano | grep :5173` permet de repérer les deux
processus en concurrence.
