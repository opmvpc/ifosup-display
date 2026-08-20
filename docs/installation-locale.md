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

- Application : <http://localhost:8000>
- Serveur Vite : <http://localhost:5173> (Laravel s'y branche automatiquement)
- MySQL exposé sur le port hôte `33062`
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

Si 8080, 8000, 5173, 33061 ou 33062 sont déjà pris, modifier la partie gauche du
mapping `ports:` dans le compose concerné.
