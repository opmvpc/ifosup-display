# STATUS — IFOSUP Display

_Dernière mise à jour : 2026-08-20_

## Où on en est

Projet de stage repris par l'école. Le code métier est fonctionnel et bien structuré
(Laravel 12 + Inertia/Vue 3, CRUD ressources, scheduler avec glisser-déposer, import
Excel, écran public `/screen`).

La reprise en main est faite : le projet tourne en Docker en local sur l'image exacte
qui partira en production, le style est à niveau, **253 tests couvrent le métier** et
les deux workflows GitHub Actions vérifient réellement quelque chose. Six bugs ont été
corrigés au passage, dont trois qui auraient bloqué ou dégradé la mise en production.

Recette locale validée par Thibault le 2026-08-20. Reste à faire : IFO-009, puis le
déploiement Coolify.

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

`bulkPreview` a par ailleurs été restreint au local demandé : ce n'était pas un bug
fonctionnel (le front refiltrait déjà), seulement du sur-transfert.

## État de la vérification

| Vérification | Résultat |
|---|---|
| `php artisan test` | **261 passés**, 7 ignorés (2FA), 0 échec — 1047 assertions |
| `composer lint:check` | PASS, 143 fichiers |
| `pnpm format:check` | PASS |
| `pnpm lint:check` | 0 erreur |
| `pnpm types:check` | 0 erreur, et vérifié par la CI |
| CI GitHub Actions | **verte** sur la PR #1 — `quality`, `ci (8.4)`, `ci (8.5)` |
| Migrations sur MySQL avec doublons réels | Dédoublonnage vérifié : le local « 106 » a conservé ses 18 attributions |
| Stack Docker locale | `/`, `/screen`, `/screen/data`, `/login` en 200 ; connexion admin OK ; CRUD vérifié en base |
| Healthcheck du container | `healthy` (il échouait en permanence avant correction) |
| Recette de l'écran public | Bonne période affichée, date en français, horloge, statut « ANNULÉ » rendu |

## Prochaine action

1. Relire et fusionner la [PR #1](https://github.com/opmvpc/ifosup-display/pull/1).
2. Ouvrir IFO-002 : créer l'application et le service MySQL 8.4 sur Coolify, renseigner
   les variables d'environnement (**`APP_KEY` généré pour la production, différent du
   local**), monter les volumes `/app/storage/app` et `/app/storage/logs`, brancher le
   domaine. Procédure complète : [`deploiement-coolify.md`](deploiement-coolify.md).
3. Arbitrer les deux points restants de la section « Points encore ouverts ».

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
