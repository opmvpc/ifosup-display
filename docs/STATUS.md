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

Reste à faire : la recette du backoffice à la main, puis le déploiement Coolify.

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

`bulkPreview` a par ailleurs été restreint au local demandé : ce n'était pas un bug
fonctionnel (le front refiltrait déjà), seulement du sur-transfert.

## État de la vérification

| Vérification | Résultat |
|---|---|
| `php artisan test` | 253 passés, 7 ignorés (2FA), 0 échec — 1033 assertions |
| `composer lint:check` | PASS, 130 fichiers |
| `pnpm format:check` | PASS |
| `pnpm lint:check` | 0 erreur |
| `pnpm types:check` | 0 erreur, et vérifié par la CI |
| Stack Docker locale | `/`, `/screen`, `/screen/data`, `/login` en 200 ; connexion admin OK ; CRUD vérifié en base |
| Healthcheck du container | `healthy` (il échouait en permanence avant correction) |
| Recette de l'écran public | Bonne période affichée, date en français, horloge, statut « ANNULÉ » rendu |

## Prochaine action

1. **Recette du backoffice, par toi** : se connecter sur <http://localhost:8080>
   (`admin@ifosup.wavre.be`), vérifier le planning et son glisser-déposer, les CRUD,
   la gestion des slides, et surtout **l'import d'un vrai fichier Excel de l'école** —
   c'est la partie la plus difficile à valider autrement qu'à la main. Les données de
   démonstration sont déjà en base.
2. Découper et livrer les commits (style / corrections / tests / infra), puis pousser :
   le push valide les deux workflows sur un vrai runner GitHub Actions.
3. Ouvrir IFO-002 : créer l'application et le service MySQL 8.4 sur Coolify, renseigner
   les variables d'environnement (**`APP_KEY` généré pour la production, différent du
   local**), monter les volumes `/app/storage/app` et `/app/storage/logs`, brancher le
   domaine. Procédure complète : [`deploiement-coolify.md`](deploiement-coolify.md).
4. Arbitrer les deux points métier ci-dessous avec l'école.

## Points laissés à l'arbitrage de l'école

- **Aucune notion de rôle** : `/admin/users` n'est protégé que par `auth` + `verified`,
  comme le reste. Tout utilisateur connecté peut créer et supprimer des comptes. Le
  comportement actuel est figé par un test, mais ce n'est pas une décision technique.
- **`purge_period`** à l'import supprime toutes les attributions de la plage de dates,
  y compris celles des locaux et cours non sélectionnés. Figé par un test le 2026-08-18,
  à confirmer avec le métier.
- **Cours mis à jour sans clé `groups`** : les sections sont détachées silencieusement.
- **`bulkStore`** écrase les attributions existantes là où `store`/`update` refusent
  en 422 ; une attribution annulée continue de bloquer son créneau.
