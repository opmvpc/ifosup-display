# Audit de la chaîne de build / déploiement

_État HEAD (095edae), 2026-08-22. Lecture seule, aucun conteneur lancé._

## Verdict global

La chaîne est globalement saine et bien mieux tenue que la moyenne d'un projet de stage :
build multi-étages sans secret en couche, volumes nommés corrects, CI qui vérifie
réellement le lint, les types et le build front. Rien ne justifie de bloquer la mise en
production, qui tourne déjà. Trois angles morts méritent une correction rapide : **aucun
`php.ini` n'est activé dans l'image** (les limites d'upload PHP sont à 2 Mo alors que
l'application en promet 20), **aucun proxy de confiance n'est déclaré** derrière Coolify,
et **les journaux applicatifs sont invisibles depuis Coolify**. Le reste relève de la
robustesse à moyen terme (migrations au boot, sonde MySQL sans identifiants).

## Problèmes confirmés

### 1. Aucun `php.ini` actif dans l'image — uploads plafonnés à 2 Mo — **important**

`Dockerfile:37-39` : le stage final installe les extensions mais ne copie jamais
`php.ini-production`. Les images officielles PHP (base de FrankenPHP) livrent
`php.ini-development` / `php.ini-production` sans en activer aucun ; PHP retombe donc sur
ses valeurs compilées : `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`.

Scénario : `SchedulerImportController.php:68` valide `'file' => [..., 'max:20480']` et
affiche « Le fichier ne doit pas dépasser 20 Mo. ». Un `.xlsx` de 3 Mo est rejeté par PHP
**avant** Laravel ; `$_FILES` arrive vide et l'utilisateur lit « Le fichier est requis. »,
message qui n'a aucun rapport avec la cause. Corroboré par `SchedulerSheetParser.php:20`,
qui doit faire `ini_set('memory_limit', '512M')` pour compenser le 128M par défaut.
Vérification : `docker compose exec app php -i | grep -E 'Loaded Config|upload_max|post_max'`.

### 2. Le serveur applicatif tourne en root — **important**

Aucune directive `USER` dans le `Dockerfile`, et `docker-entrypoint.sh:40` fait
`exec frankenphp run` sans changement d'utilisateur. Tout le code PHP s'exécute donc en
root, avec les volumes `storage/app` montés. Corollaire : le `chown -R www-data`
(`Dockerfile:48`, `docker-entrypoint.sh:8`) n'a aucun effet pratique puisque le process qui
écrit est root — les fichiers importés finissent root. Une RCE dans l'application donne
root dans le conteneur.

### 3. Aucun proxy de confiance déclaré — **important**

`bootstrap/app.php:16-24` n'appelle pas `$middleware->trustProxies(...)` et il n'existe pas
de `config/trustedproxy.php`. Derrière le proxy Coolify, `X-Forwarded-Proto` et
`X-Forwarded-For` sont donc ignorés : `$request->isSecure()` vaut `false` et
`$request->ip()` renvoie l'IP du proxy. Le `URL::forceScheme('https')` de
`AppServiceProvider.php:28-30` masque le symptôme le plus visible (les URLs générées), mais
il en reste deux :

- `config/session.php:172` lit `SESSION_SECURE_COOKIE`, absent de
  `docker-compose.coolify.yml` ⇒ `null` ⇒ le cookie de session **n'est pas marqué `Secure`**.
- Toute journalisation ou limitation par IP voit une seule et même adresse.

Le throttle de connexion reste correct : `FortifyServiceProvider.php:61-65` combine
e-mail + IP, donc le compte reste protégé même avec une IP constante.

### 4. Sonde MySQL verte alors que l'application ne peut pas se connecter — **important**

`docker-compose.coolify.yml:91` : `mysqladmin ping -h 127.0.0.1` sans identifiants renvoie 0
dès que le serveur répond, « Access denied » compris — le commentaire du fichier l'assume.
La sonde prouve que le protocole répond, pas que les identifiants applicatifs marchent.

Scénario non couvert par le guide : on change `DB_PASSWORD` dans Coolify après le premier
déploiement. MySQL a figé le mot de passe de `ifosup` à l'initialisation du volume
(`MYSQL_PASSWORD` n'agit qu'au premier boot) ⇒ sonde verte ⇒ `app` démarre ⇒ `db:show`
échoue 60 fois (120 s) ⇒ `exit 1` (`docker-entrypoint.sh:18-21`) ⇒ `restart: unless-stopped`
relance en boucle. `docs/deploiement-coolify.md:184-186` ne documente ce gel que pour le mot
de passe **root**.

### 5. Journaux applicatifs invisibles et jamais purgés — **important**

`docker-compose.coolify.yml:40-42` : `LOG_CHANNEL=stack` / `LOG_STACK=single` écrit dans
`storage/logs/laravel.log`, sur le volume `ifosup-logs`. Rien n'apparaît dans `docker logs`
ni dans l'onglet Logs de Coolify, qui ne voit que Caddy et l'entrypoint : diagnostiquer une
500 impose un `docker exec`. Et le canal `single` est un fichier unique, sans rotation ni
logrotate dans le conteneur — il grossit indéfiniment sur le volume. `LOG_CHANNEL=stderr`
réglerait les deux points d'un coup.

### 6. `migrate --force` au démarrage, sans verrou — **important (latent)**

`docker-entrypoint.sh:27` : chaque conteneur migre au boot. Avec un replica unique c'est
sain. Dès qu'il y en a deux, ou pendant un déploiement où ancien et nouveau conteneur
coexistent, deux `migrate` concurrents peuvent jouer la même migration — Laravel ne pose
aucun verrou. Par ailleurs une migration en échec fait sortir l'entrypoint, et
`restart: unless-stopped` relance sans fin, sans état d'erreur exploitable.

### 7. `node_modules` embarqué dans une couche de l'image — **mineur**

`Dockerfile:44-45` : `COPY --from=builder /app /app` copie `node_modules`, et le `rm -rf`
suivant ajoute une couche de suppression sans réduire la taille transférée. Exclure le
dossier au `COPY` (ou le supprimer dans le stage builder) économiserait plusieurs centaines
de Mo par déploiement.

### 8. Écarts guide / réalité — **mineur**

- `docs/deploiement-coolify.md:120-124` : `docker exec ... mysqldump -u root -p"$DB_ROOT_PASSWORD"`
  — la variable est développée par le shell de **l'hôte**, où elle n'existe pas ; côté
  conteneur elle s'appelle `MYSQL_ROOT_PASSWORD`. Le dump partirait avec un mot de passe vide.
- `docs/deploiement-coolify.md:59-61` affirme qu'`ADMIN_PASSWORD` doit respecter la politique
  `Password::defaults()`. `CreateAdminUser.php:33-46` fait un `Hash::make` direct, sans aucune
  validation : rien ne l'impose. Le conseil reste bon, l'affirmation est fausse.
- `README.md:46` et `CLAUDE.md` annoncent la stack dev sur `localhost:8000` ; le défaut réel
  est `8001` (`docker-compose.dev.yml:22`, conforme à `docs/installation-locale.md:36`).

### 9. Stack dev : le MySQL démarré n'est pas celui qu'utilise l'application — **mineur**

`docker-compose.dev.yml:12-27` n'injecte aucun `DB_*` : le conteneur lit le `.env` monté, que
`docker/dev-entrypoint.sh:6-9` crée depuis `.env.example` (`DB_CONNECTION=sqlite`). Un poste
neuf développe donc sur SQLite pendant que le conteneur MySQL 8.4 tourne à vide sur le port
33063 — exactement le scénario qui a laissé passer IFO-006 (migration cassée uniquement sur
MySQL), et contraire à ADR-001. Le guide ne précise pas quoi mettre dans `.env`.

## Points de vigilance

- **La CI ne construit jamais l'image Docker** : aucun workflow ne fait `docker build`. Une
  régression du `Dockerfile` ou de `docker-entrypoint.sh` n'apparaît qu'au déploiement.
- **La CI ne teste que SQLite** (`phpunit.xml:27-28`) alors que la production est MySQL 8.4 :
  la classe de bug IFO-006 reste structurellement invisible.
- **Le healthcheck applicatif** (`Dockerfile:62-63`) interroge `/`, qui passe par le groupe
  `web` et donc par la session en base : une ligne `sessions` créée toutes les 30 s (purgée
  par la loterie de GC, donc bornée). `/up` est déjà déclaré (`bootstrap/app.php:14`) et
  serait plus adapté.
- **`ADMIN_PASSWORD` reste dans l'environnement du conteneur** après le premier boot, lisible
  via `docker inspect`. Le guide dit de changer le mot de passe, pas de retirer la variable.
- **`app:create-admin-user` lit `env()` directement** : cela ne fonctionne que parce que
  l'entrypoint l'appelle (`:30`) avant `optimize` (`:35`). Inverser l'ordre casserait la
  création du compte silencieusement.
- **Rotation d'`APP_KEY`** : aucun attribut chiffré en base (vérifié). Seule conséquence,
  tous les utilisateurs sont déconnectés (cookies non déchiffrables). Aucune perte de données.
- `tests.yml` n'a pas de bloc `permissions:` contrairement à `lint.yml` ; aucun `concurrency`
  sur les deux workflows. Pas de limites CPU/mémoire ni de rotation des logs Docker au compose.

## Ce qui est sain

- **Pas de secret en couche d'image** : `Dockerfile:30-34` crée le `.env` jetable et la clé de
  build puis les supprime dans le **même** `RUN` — rien ne persiste, et le stage builder n'est
  de toute façon pas expédié. `.dockerignore` exclut `.env*` (sauf l'exemple), `.git`, `docs`,
  `public/build` et les dossiers Wayfinder, qui sont donc bien régénérés.
- **Wayfinder** : PHP est pleinement fonctionnel au moment du `pnpm build` (autoload dumpé,
  `.env` + clé présents), et `laravel/wayfinder` est en `require`, pas `require-dev` — le
  `--no-dev` ne le retire pas. 40 fichiers front consomment `@/actions|routes|wayfinder`.
- **`pnpm types:check` ne casse pas en CI** : `lint.yml:63-64` génère Wayfinder avant, et
  `tests.yml:63-64` lance `pnpm build`. L'ordre `composer install` → `key:generate` → build est
  correct dans les deux workflows, PHP 8.4 partout (matrice 8.4/8.5 côté tests).
- **Compose Coolify** : aucun port publié sur l'hôte (`expose: '80'`, lignes 66-67),
  `depends_on: service_healthy`, trois volumes nommés couvrant `storage/app`, `storage/logs` et
  `/var/lib/mysql`, aucun secret en dur, et aucune instruction `VOLUME` dans le `Dockerfile`.
- **Le healthcheck hérité de FrankenPHP est bien neutralisé** : le `Caddyfile` coupe l'API
  d'admin, le `HEALTHCHECK` du projet interroge l'application.
- **Aucune tâche planifiée ni job en file d'attente** dans le code (vérifié) : l'absence de
  worker et de `schedule:work` au compose n'est pas un manque.
- **Le guide décrit fidèlement le compose** : variables obligatoires, valeurs par défaut,
  volumes et ordre exact des étapes de l'entrypoint correspondent au fichier réel.
