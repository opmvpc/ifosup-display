# STATUS — IFOSUP Display

_Dernière mise à jour : 2026-08-24_

## Hotfix en cours (2026-08-24)

Les médias de slides répondaient 404 en production : régression du conteneur
non-root d'IFO-015 — `artisan storage:link` échouait en silence (`|| true`) en
`www-data` contre un `/app/public` root, le lien `public/storage` n'était plus
créé. Correctif poussé sur `main` (lien posé en root dans l'entrypoint) :
[IFO-017](tickets/IFO-017-storage-link-non-root.md). À vérifier après
redéploiement Coolify ; les fichiers uploadés sont intacts sur le volume.

## Où on en est

Projet de stage repris par l'école. Le code métier est fonctionnel et bien structuré
(Laravel 12 + Inertia/Vue 3, CRUD ressources, scheduler avec glisser-déposer, import
Excel, écran public `/screen`).

La reprise en main est faite : le projet tourne en Docker en local sur l'image exacte
qui partira en production, le style est à niveau, **284 tests couvrent le métier** et
les deux workflows GitHub Actions vérifient réellement quelque chose — ils sont verts
sur un vrai runner. **Quatorze bugs** ont été corrigés au passage, dont quatre qui auraient
bloqué ou dégradé la mise en production, et un introduit puis rattrapé en cours de
reprise.

Recette locale validée par Thibault le 2026-08-20. Seule reste ouverte la recette de
production (IFO-002), attendue pour les premiers tests réels de lundi.

Le site est **en production sur le Coolify de l'école** depuis le 2026-08-21/22 (recette
complète encore à dérouler). Le 2026-08-22, un **audit croisé multi-modèles** (deux
agents Fable, un Opus, un Codex GPT-5.6 Sol) a contre-vérifié la reprise : verdict
**fiable**, chaque fix revendiqué est réel, aucune régression majeure. Trois vraies
prises corrigées dans la foulée (migration renommée qui ressuscitait une table sur les
bases pré-reprise, fusion de pivots dupliquant des lignes, uploads plafonnés à 2 Mo
faute de `php.ini` dans l'image) plus trusted proxies, cookie `Secure`, garde-fous de
suppression de comptes et logs visibles dans Coolify. Détail :
[IFO-013](tickets/IFO-013-audit-croise-reprise.md).

**PR #4 fusionnée et redéployée le 2026-08-22 (~16h)** : déploiement Coolify
`finished`, application `running:healthy`, vérifié depuis l'extérieur — routes en
200, cookie de session `secure; httponly`, en-têtes de sécurité présents, balises
OG en https, favicon et image de partage servis, « Nothing to migrate ». CI de
main verte, y compris les nouveaux jobs `ci-mysql` et `docker-image`.

Les pages du starter kit (settings, suppression de compte, confirmation de mot de
passe, menu utilisateur) sont traduites en français
([IFO-012](tickets/IFO-012-traduction-fr-starter-kit.md)).

## Chantiers

| Ticket | Titre | Statut |
|---|---|---|
| [IFO-001](tickets/IFO-001-docker-local.md) | Environnement Docker local (prod-like + dev) | terminé |
| [IFO-002](tickets/IFO-002-deploiement-coolify.md) | Déploiement sur le Coolify de l'école | **ouvert** |
| [IFO-003](tickets/IFO-003-route-debug-excel.md) | Retirer la route publique `/debug-excel` | terminé |
| [IFO-004](tickets/IFO-004-couverture-de-tests.md) | Couverture de tests du métier | terminé |
| [IFO-005](tickets/IFO-005-persistance-storage.md) | Persistance des fichiers uploadés | terminé |
| [IFO-006](tickets/IFO-006-ordre-migrations.md) | Ordre des migrations cassé sur MySQL | terminé |
| [IFO-007](tickets/IFO-007-ci-au-vert.md) | CI GitHub Actions au vert | terminé |
| [IFO-008](tickets/IFO-008-typecheck-et-reliquats-2fa.md) | `types:check` en CI | terminé |
| [IFO-009](tickets/IFO-009-import-sans-transaction.md) | Import de planning hors transaction | terminé |
| [IFO-010](tickets/IFO-010-contraintes-unicite.md) | Contraintes d'unicité manquantes en base | terminé |
| [IFO-011](tickets/IFO-011-messages-de-validation.md) | Messages de validation et erreurs 422 | terminé |
| [IFO-012](tickets/IFO-012-traduction-fr-starter-kit.md) | Traduction française du starter kit | terminé |
| [IFO-013](tickets/IFO-013-audit-croise-reprise.md) | Audit croisé de la reprise (multi-modèles) | terminé |
| [IFO-014](tickets/IFO-014-import-sensible-casse.md) | Import Excel sensible à la casse (500 / lignes ignorées) | terminé |
| [IFO-015](tickets/IFO-015-durcissements-differes.md) | Durcissements de l'audit croisé (12/14, deux restes assumés) | terminé |
| [IFO-016](tickets/IFO-016-favicon-og-appname.md) | Favicon de marque, carte OG, nom d'application en dur | terminé |
| [IFO-017](tickets/IFO-017-storage-link-non-root.md) | Médias de slides en 404 (storage:link vs non-root) | **en vérification** |

## Décisions

- [ADR-001](decisions/ADR-001-mysql-comme-base.md) — MySQL 8.4 en local et en production
- [ADR-002](decisions/ADR-002-deux-stacks-docker.md) — deux stacks Docker distinctes

## Bugs corrigés

| Ticket / phase | Bug | Portée |
|---|---|---|
| IFO-006 | `create_assignments` référençait `recurring_assignments`, créée dix jours plus tard dans l'ordre des migrations. Invisible en SQLite, bloquant sur MySQL. | Empêchait toute installation neuve en production |
| IFO-003 | `/debug-excel` publique, hors `auth`, avec un `dd()` sur le planning importé | Fuite de données sans authentification |
| Phase 3 (B1) | Les trois plages horaires de `ScreenController` s'arrêtaient à la seconde pile : à 23:59:59,5 aucune ne correspondait | L'écran se vidait environ une seconde chaque nuit |
| Phase 3 (B3) | Les hooks de `ScreenSlide` supprimaient les médias sur le disque `local` alors qu'ils sont stockés sur `public` | Fichiers orphelins accumulés indéfiniment |
| Phase 3 (bonus) | `FortifyServiceProvider` n'enregistrait que `loginView` : la route `password.confirm` terminait en 500 alors que la page Inertia existait | Erreur serveur sur la confirmation de mot de passe |
| 2026-08-20 | Le healthcheck hérité de l'image FrankenPHP interrogeait l'API d'admin de Caddy, désactivée par le `Caddyfile` (`admin off`) : le container était toujours `unhealthy` | Coolify aurait pu marquer les déploiements en échec ou redémarrer le container en boucle |
| IFO-009 | L'import purgeait puis réinsérait hors transaction, sans `SoftDeletes` | Perte de données irrécupérable si l'import échouait à mi-parcours |
| IFO-010 | Aucune unicité sur les noms de locaux : `pluck('id','name')` en retenait un au hasard | L'import Excel pouvait rattacher un planning au mauvais local |
| IFO-010 | Aucune unicité sur le créneau (date, période, local) | Deux écritures concurrentes plaçaient deux cours dans le même local, affichés tous deux sur les TV |
| IFO-011 | Aucun fichier de langue malgré `APP_LOCALE=fr` | Une douzaine de formulaires affichaient `validation.required` à l'écran |
| IFO-011 | Les appels hors Inertia jetaient le détail des erreurs 422 | L'utilisateur voyait « Une erreur est survenue. » sans savoir quoi corriger |
| IFO-011 | `gte:start_week` comparait la longueur des chaînes, jamais les dates | Une plage de semaines inversée était acceptée en silence |
| IFO-010 | Contraintes d'unicité posées en base sans règle `unique` applicative | Créer un doublon renvoyait une erreur 500 au lieu d'un message sous le champ (trouvé en recette) |
| 2026-08-21 | La sonde MySQL portait le mot de passe root, sans utilité : `mysqladmin ping` renvoie 0 même sur « Access denied » | Secret exposé dans `docker inspect` et interpolation `${...}` superflue confiée au parseur de Coolify |

`bulkPreview` a par ailleurs été restreint au local demandé : ce n'était pas un bug
fonctionnel (le front refiltrait déjà), seulement du sur-transfert.

## État de la vérification

| Vérification | Résultat |
|---|---|
| `php artisan test` | **284 passés**, 0 ignoré (reliquats 2FA supprimés), 0 échec — 1133 assertions |
| `composer lint:check` | PASS, 145 fichiers |
| `pnpm format:check` | PASS |
| `pnpm lint:check` | 0 erreur |
| `pnpm types:check` | 0 erreur, et vérifié par la CI |
| CI GitHub Actions | **verte** sur la PR #1 — `quality`, `ci (8.4)`, `ci (8.5)` |
| Migrations sur MySQL avec doublons réels | Dédoublonnage vérifié : le local « 106 » a conservé ses 18 attributions |
| Stack Docker locale | `/`, `/screen`, `/screen/data`, `/login` en 200 ; connexion admin OK ; CRUD vérifié en base |
| Healthcheck du container | `healthy` (il échouait en permanence avant correction) |
| Recette de l'écran public | Bonne période affichée, date en français, horloge, statut « ANNULÉ » rendu |
| `docker-compose.coolify.yml` rejoué en local (2026-08-21) | MySQL `healthy`, application `healthy`, migrations passées, compte admin créé, `/`, `/screen` et `/login` en 200 |
| Image reconstruite après l'audit, rejouée en local (2026-08-22) | `healthy` (sonde `/up`), toutes les routes en 200, migrations (dont les deux corrigées) passées sur MySQL, `php.ini` chargé : upload 300M / post 310M / mémoire 512M |
| Image non-root rejouée en local (2026-08-22, IFO-015) | PID 1 = frankenphp en `www-data`, routes en 200, en-têtes de sécurité présents, écritures `storage` OK sur des volumes contenant des fichiers créés en root |

La seconde vague du 2026-08-22 (demande de Thibault, tests réels prévus lundi) a
ensuite tout soldé : [IFO-014](tickets/IFO-014-import-sensible-casse.md) (import
insensible à la casse), [IFO-015](tickets/IFO-015-durcissements-differes.md)
(12 durcissements sur 14 : conteneur non-root, en-têtes de sécurité, throttle et
sérialisation de l'écran public, invalidation des sessions, verrou des slides par
défaut, 422 sur course de créneau, suppression des reliquats 2FA, CI MySQL + build
d'image, stacks locales assainies) et
[IFO-016](tickets/IFO-016-favicon-og-appname.md) (favicon de marque, carte de
partage OG, nom d'application en dur — l'interface Coolify refusant les espaces
dans les variables).

## Prochaine action

1. Dérouler les vérifications de la section 6 du guide (connexion admin, `/screen`
   depuis une TV, persistance après redéploiement, sauvegarde planifiée) — la recette
   de production n'a pas encore été faite. Tester en particulier l'upload d'une vidéo
   de slide (> 2 Mo), cassé avant le correctif php.ini.
2. Vider `ADMIN_EMAIL`/`ADMIN_PASSWORD` dans l'interface Coolify (reste assumé
   d'IFO-015) — sans risque : la commande saute proprement l'étape quand les
   variables sont vides ; ne les re-renseigner que si le volume MySQL repart de zéro.
3. Facultatif : ajouter `chore/**` et `feat/**` aux déclencheurs `push` des
   workflows, pour obtenir un retour de CI sans devoir ouvrir une PR.

## Décisions métier rendues

- **Pas de gestion de rôles** (2026-08-20) : tout utilisateur connecté peut gérer les
  comptes via `/admin/users`. Arbitré par l'école, le comportement actuel est conservé
  et figé par un test. À rouvrir seulement si le nombre de comptes augmente.
- **`purge_period`** (2026-08-20) : la purge de toute la plage de dates est délibérée et
  correctement signalée dans l'interface (encadré « danger zone », mention « sans retour
  en arrière possible », compteur exact des enregistrements concernés). Ce n'était pas un
  défaut. Le vrai problème identifié en l'examinant est l'absence de transaction, suivi
  sous [IFO-009](tickets/IFO-009-import-sans-transaction.md).

## Points examinés puis écartés

Trois comportements signalés comme « surprenants » par les analyses se sont révélés
assumés une fois confrontés à ce que voit réellement l'utilisateur. Ils restent figés
par des tests, mais ne sont pas des défauts :

- **`purge_period`** : encadré « danger zone », mention « sans retour en arrière
  possible », compteur exact des enregistrements supprimés.
- **`bulkStore` qui remplace** là où `store` refuse : l'écran annonce « N cours
  existants seront remplacés, cette action est destructive » et le bouton lui-même
  indique « Insérer (N) et Remplacer (M) ». Les deux comportements répondent à deux
  intentions distinctes — empêcher l'écrasement accidentel d'un côté, permettre le
  remplacement voulu de l'autre.
- **`sync([])` implicite sur les sections d'un cours** : la clé `groups` n'est absente
  de la requête que lorsque l'utilisateur a décoché toutes les sections — le composant
  `Combobox` n'émet alors aucun champ. Détacher toutes les sections est donc le
  comportement attendu. Le cas gênant serait une mise à jour partielle sans `groups` :
  l'interface n'en produit pas.

Leçon retenue, valable pour la suite : un comportement ne devient un défaut qu'après
confrontation avec l'interface réelle. Trois signalements sur trois se sont dégonflés.

## Détail mineur non traité

`bulkStore` renvoie `inserted` = nombre de lignes demandées, sans distinguer créations
et remplacements, ni signaler les suppressions. Sans conséquence : le front affiche son
propre décompte avant validation.
