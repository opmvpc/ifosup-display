# Déploiement sur Coolify

Procédure de mise en ligne de IFOSUP Display sur l'instance Coolify de l'école.
Ticket associé : [IFO-002](tickets/IFO-002-deploiement-coolify.md).

L'application **et** sa base de données sont décrites dans un seul fichier versionné,
`docker-compose.coolify.yml`. Il n'y a donc pas de ressource MySQL à créer séparément
dans Coolify : tout part du dépôt, y compris les volumes persistants.

## 1. Créer l'application

- **Source** : dépôt GitHub `opmvpc/ifosup-display`, branche `main`.
- **Build pack** : **Docker Compose**, fichier `docker-compose.coolify.yml`.
- **Port exposé** : `80` (déclaré par `expose` dans le compose).
- **Domaine** : le domaine de l'école, HTTPS activé — Coolify gère le certificat et
  renseigne `SERVICE_FQDN_APP_80`, dont l'application tire son `APP_URL`.

Les fichiers `docker-compose.yml` et `docker-compose.dev.yml` du dépôt servent
uniquement au poste local : ne pas les sélectionner ici.

## 2. Variables d'environnement

**Trois variables sont obligatoires.** Elles apparaissent comme des champs vides dans
l'interface Coolify, qui lit le compose pour pré-remplir la liste.

| Variable | Valeur |
|---|---|
| `APP_KEY` | à générer pour la production, **différente du poste local** |
| `DB_PASSWORD` | mot de passe de l'utilisateur applicatif MySQL |
| `DB_ROOT_PASSWORD` | mot de passe root MySQL |

Génération de la clé applicative :

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Laissées vides, elles ne produisent pas de démarrage silencieux : l'image MySQL refuse
de démarrer (« You need to specify one of the following as an environment variable:
MYSQL_ROOT_PASSWORD… ») et Laravel s'arrête sur « No application encryption key has been
specified. »

Le compose les déclare volontairement sous la forme `${VAR}`, sans valeur par défaut.
La forme `${VAR:?message}`, pourtant standard en Docker Compose pour signaler une
variable obligatoire, est à proscrire ici : Coolify ne l'interprète pas comme une
exigence et **pré-remplit le champ avec le message lui-même**, qui deviendrait alors le
mot de passe.

**Deux variables sont indispensables au premier déploiement seulement** — elles créent
le compte administrateur si la table `users` est vide, et n'ont plus aucun effet ensuite :

| Variable | Valeur |
|---|---|
| `ADMIN_EMAIL` | adresse du premier administrateur |
| `ADMIN_PASSWORD` | mot de passe fort, **à changer après la première connexion** |

`APP_ENV` valant `production` par défaut, `AppServiceProvider` impose une politique de
mot de passe stricte : 12 caractères minimum, casse mixte, chiffres, symboles, et absence
des bases de mots de passe compromis. `ADMIN_PASSWORD` doit la respecter.

**Tout le reste a une valeur par défaut** adaptée à la production, à ne renseigner que
pour s'en écarter :

| Variable | Défaut |
|---|---|
| `APP_NAME` | `Ifosup Display` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | le domaine attribué par Coolify (`SERVICE_FQDN_APP_80`) |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `fr` |
| `APP_SCREEN_TIMEZONE` | `Europe/Brussels` |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | `stack` / `single` / `warning` |
| `DB_DATABASE` / `DB_USERNAME` | `ifosup_display` / `ifosup` |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` |
| `SESSION_LIFETIME` | `120` |
| `FILESYSTEM_DISK` | `local` |
| `MAIL_MAILER` | `log` — aucun serveur mail n'est configuré (cf. `config/fortify.php`) |

`DB_CONNECTION`, `DB_HOST` et `DB_PORT` ne sont **pas** paramétrables : ils pointent sur
le service `mysql` du compose et sont figés pour éviter toute désynchronisation entre
l'application et sa base.

## 3. Volumes persistants

Ils sont déclarés dans le compose, donc créés automatiquement. Rien à faire dans
l'interface. Coolify recrée les conteneurs à chaque déploiement, jamais les volumes.

| Volume | Monté sur | Contenu |
|---|---|---|
| `ifosup-storage` | `/app/storage/app` | plannings importés et médias des slides |
| `ifosup-logs` | `/app/storage/logs` | journaux applicatifs |
| `ifosup-mysql` | `/var/lib/mysql` | la base de données |

Sans eux, chaque redéploiement effacerait plannings et médias
(ticket [IFO-005](tickets/IFO-005-persistance-storage.md)).

### Ce qu'il ne faut surtout pas faire

Déclarer ces chemins avec l'instruction `VOLUME` du `Dockerfile`. Elle crée un volume
**anonyme**, c'est-à-dire neuf et vide à chaque conteneur. Vérifié :

```
run 1 → fichier.txt contient 1 ligne
run 2 → fichier.txt contient 1 ligne   (et non 2)
```

Les données seraient perdues à chaque redéploiement et les volumes orphelins
s'accumuleraient. C'est l'exact inverse de l'effet recherché.

## 4. Sauvegardes

La base vivant dans le compose plutôt que dans une ressource Coolify managée, les
sauvegardes automatiques de Coolify **ne s'appliquent pas** : c'est un choix assumé
(décision du 2026-08-20), à compenser par une sauvegarde externe.

Un dump depuis le serveur, à planifier par cron :

```bash
docker exec <conteneur-mysql> \
  mysqldump -u root -p"$DB_ROOT_PASSWORD" --single-transaction ifosup_display \
  | gzip > ifosup-$(date +%F).sql.gz
```

`--single-transaction` évite de verrouiller les tables pendant le dump. Penser à
sauvegarder aussi le volume `ifosup-storage` : il contient les plannings importés et les
médias des slides, que la base seule ne suffit pas à reconstituer.

## 5. Ce que fait le conteneur au démarrage

`docker-entrypoint.sh`, dans l'ordre : création des dossiers `storage/`, attente que la
base réponde (60 tentatives espacées de 2 s), `migrate --force`,
`app:create-admin-user` (uniquement si la table `users` est vide), `storage:link --force`,
`optimize:clear`, `optimize`, puis `frankenphp run`.

Le premier déploiement crée donc le schéma et le compte administrateur tout seul. Le
service `app` attend que `mysql` soit sain avant de démarrer, ce qui évite que les
migrations partent trop tôt.

Un `HEALTHCHECK` interroge l'application elle-même. L'image FrankenPHP de base testait
l'API d'administration de Caddy, que le `Caddyfile` du projet désactive : le conteneur
restait alors éternellement `unhealthy`, ce qu'un orchestrateur peut interpréter comme
un déploiement raté.

## 6. Vérifications après déploiement

- [ ] `https://<domaine>/` répond 200 et le conteneur passe `healthy`
- [ ] `https://<domaine>/login` permet de se connecter avec `ADMIN_EMAIL`
- [ ] Le mot de passe administrateur a été changé après la première connexion
- [ ] `https://<domaine>/screen` affiche l'écran public sans authentification
- [ ] Créer un local enregistre bien en base, et un nom en double affiche
      « Un local porte déjà ce nom. » sous le champ
- [ ] Un import de planning aboutit sur un vrai fichier Excel de l'école
- [ ] Après un redéploiement, un fichier importé est toujours présent
- [ ] Une sauvegarde a été planifiée (section 4)
