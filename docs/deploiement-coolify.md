# Déploiement sur Coolify

Procédure de mise en ligne de IFOSUP Display sur l'instance Coolify de l'école.
Ticket associé : [IFO-002](tickets/IFO-002-deploiement-coolify.md).

## 1. Service base de données

Créer d'abord une ressource **MySQL 8.4** dans le même projet Coolify. Noter le nom
d'hôte interne attribué par Coolify, la base, l'utilisateur et le mot de passe :
ils alimentent les variables `DB_*` de l'application.

## 2. Application

- **Source** : dépôt GitHub `opmvpc/ifosup-display`, branche `main`.
- **Build pack** : `Docker Compose` avec `docker-compose.coolify.yml` (recommandé, voir
  la section 4), ou `Dockerfile` en déclarant les volumes dans l'interface.
  `docker-compose.yml` et `docker-compose.dev.yml` servent uniquement au poste local.
- **Port exposé** : `80`.
- **Domaine** : le domaine de l'école, HTTPS activé (Coolify gère le certificat).

## 3. Variables d'environnement

À renseigner dans l'onglet *Environment Variables*. La `APP_KEY` doit être
**générée pour la production** et différente de celle du poste local :

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

| Variable | Valeur |
|---|---|
| `APP_NAME` | `Ifosup Display` |
| `APP_ENV` | `production` |
| `APP_KEY` | la clé générée ci-dessus |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<domaine>` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `fr` |
| `APP_SCREEN_TIMEZONE` | `Europe/Brussels` |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | `stack` / `single` / `warning` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | valeurs du service MySQL |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `local` |
| `MAIL_MAILER` | `log` (aucun serveur mail configuré, cf. `config/fortify.php`) |
| `ADMIN_EMAIL` | l'adresse du premier administrateur |
| `ADMIN_PASSWORD` | mot de passe fort, **à changer après la première connexion** |

`APP_ENV=production` déclenche `URL::forceScheme('https')` dans `AppServiceProvider`
et la politique de mots de passe stricte (12 caractères, casse mixte, chiffres,
symboles, non compromis) : `ADMIN_PASSWORD` doit la respecter.

## 4. Volumes persistants

Sans volume, chaque redéploiement efface les plannings importés et les médias des
slides (ticket [IFO-005](tickets/IFO-005-persistance-storage.md)) :

| Chemin dans le container | Usage |
|---|---|
| `/app/storage/app` | fichiers importés et médias des slides |
| `/app/storage/logs` | journaux applicatifs |

Deux façons de les obtenir, au choix.

### Option A — les déclarer dans le dépôt (recommandé)

Sélectionner le build pack **« Docker Compose »** dans Coolify et pointer sur
`docker-compose.coolify.yml`, versionné à la racine. Il déclare les deux volumes
nommés, l'exposition du port 80 et la liste des variables attendues. Avantage : la
configuration de persistance vit dans le dépôt, personne ne peut l'oublier en recréant
l'application.

Ce fichier refuse de démarrer si `APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` ou
`DB_PASSWORD` manquent — le déploiement échoue avec un message explicite plutôt que de
lancer une application cassée. Comportement vérifié :

```
error while interpolating services.app.environment.[]:
required variable APP_KEY is missing a value: APP_KEY doit etre defini dans Coolify
```

### Option B — les déclarer dans l'interface

En restant sur le build pack « Dockerfile », ajouter les deux volumes dans l'onglet
*Storages* de l'application. C'est une manipulation unique, mais elle n'est écrite
nulle part dans le dépôt : à refaire si l'application est recréée.

### Ce qu'il ne faut surtout pas faire

Déclarer ces chemins avec l'instruction `VOLUME` du `Dockerfile`. Elle crée un volume
**anonyme**, c'est-à-dire un volume neuf et vide à chaque conteneur. Vérifié :

```
run 1 → fichier.txt contient 1 ligne
run 2 → fichier.txt contient 1 ligne   (et non 2)
```

Les données seraient donc perdues à chaque redéploiement, et les volumes orphelins
s'accumuleraient sur le serveur. C'est l'exact inverse de l'effet recherché.

## 5. Ce que fait le container au démarrage

`docker-entrypoint.sh`, dans l'ordre : création des dossiers `storage/`, attente de
la base (60 tentatives × 2 s), `migrate --force`, `app:create-admin-user` (seulement
si la table `users` est vide), `storage:link --force`, `optimize:clear`, `optimize`,
puis `frankenphp run`.

Le premier déploiement crée donc le schéma et le compte administrateur tout seul.

## 6. Vérifications après déploiement

- [ ] `https://<domaine>/` répond 200
- [ ] `https://<domaine>/login` permet de se connecter avec `ADMIN_EMAIL`
- [ ] `https://<domaine>/screen` affiche l'écran public sans authentification
- [ ] Un CRUD (salle, enseignant) enregistre bien en base
- [ ] Après un redéploiement, un fichier importé est toujours présent

## 7. Avant la mise en ligne

Traiter [IFO-003](tickets/IFO-003-route-debug-excel.md) : la route `/debug-excel`
est publique et dumpe le contenu du planning importé.
