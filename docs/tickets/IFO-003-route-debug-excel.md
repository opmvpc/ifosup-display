---
id: IFO-003
titre: Retirer la route publique /debug-excel
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

`routes/web.php` déclare `Route::get('/debug-excel', [SchedulerParserController::class, 'debug'])`
**hors** du groupe `auth`. La méthode lit `storage/app/private/planning.xlsx` et
termine par un `dd()` : n'importe qui sur Internet peut déclencher le parsing et
lire le contenu du planning importé. `dd()` n'est pas conditionné par `APP_DEBUG`,
la fuite existe donc aussi en production.

## Critères d'acceptation

- [x] La route est supprimée, ainsi que son import dans `routes/web.php`
- [x] `SchedulerParserController` supprimé (il ne contenait que `debug()`, sans autre appelant)
- [x] `GET /debug-excel` renvoie 404 (vérifié sur la stack Docker)
- [ ] Un test de non-régression fige l'absence de cette route — à intégrer au chantier [[IFO-004-couverture-de-tests]]

## Journal du ticket

- 2026-08-18 — création (repérée pendant l'audit initial du repo).
- 2026-08-18 — corrigé. Route et `use` retirés de `routes/web.php`,
  `app/Http/Controllers/SchedulerParserController.php` supprimé. Confirmé en 404 sur
  la stack locale. L'analyse du module import a indépendamment signalé la même faille.
