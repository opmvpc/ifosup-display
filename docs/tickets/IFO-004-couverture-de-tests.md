---
id: IFO-004
titre: Couverture de tests du métier (scheduler, écran, import Excel)
statut: terminé
priorité: normale
dépend-de: []
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

`tests/` ne contient que les tests fournis par le starter kit Laravel
(authentification, profil, 2FA). Aucun test ne couvre le cœur du projet :
`ScheduleController`, `ScreenController`, `ScreenSlideController`,
`SchedulerImportController` ni le service `SchedulerSheetParser`. Toute reprise
du code se fait donc à l'aveugle.

Plan détaillé et priorisé : [`../plan-tests-et-ci.md`](../plan-tests-et-ci.md).
Analyses d'origine : [`../research/tests-scheduler.md`](../research/tests-scheduler.md),
[`../research/tests-import-et-ecran.md`](../research/tests-import-et-ecran.md),
[`../research/tests-crud-et-ci.md`](../research/tests-crud-et-ci.md).

Point de départ mesuré : `php artisan test` donne **23 échecs, 7 ignorés, 11 succès**,
la quasi-totalité des échecs venant de `Vite manifest not found` — la suite PHP dépend
aujourd'hui d'un `npm run build` préalable.

## Critères d'acceptation

- [x] Socle : `withoutVite()` global, `ScreenSlideFactory`, states `AssignmentFactory`,
      helper d'authentification, helper de fixture Excel (phase 2 du plan)
- [x] Les tests du starter kit encore pertinents repassent au vert
- [x] Tests des CRUD ressources (teachers, rooms, groups, courses) — accès refusé
      aux anonymes, création/édition/suppression OK pour un utilisateur connecté
- [x] Tests de `ScheduleController` : store, update, updateStatus, destroy, bulk
- [x] Test de `ScreenController::data()` sur les trois périodes, avec les frontières
      12:30 / 17:30 / minuit couvertes par `travelTo`
- [x] Tests de `ScreenSlideController` (CRUD, réordonnancement, médias)
- [x] Tests de l'import Excel (upload, preview, execute, discard) et du parser
- [x] Non-régression : `GET /debug-excel` renvoie 404
- [x] `php artisan test` vert : **253 tests passés, 7 ignorés, 0 échec** (1033 assertions)

## Journal du ticket

- 2026-08-18 — création (repérée pendant l'audit initial du repo).
- 2026-08-18 — trois analyses parallèles rendues (scheduler, import/écran, CRUD/CI),
  216 tests proposés au total. Plan consolidé retenant un noyau de ~110 tests écrit dans
  `docs/plan-tests-et-ci.md`. Trois bugs confirmés à corriger au passage (voir phase 3),
  six comportements surprenants à figer par test sans les modifier.
- 2026-08-18 — implémenté. Socle posé (`withoutVite()` global, `ScreenSlideFactory`,
  states `AssignmentFactory`, helper `actingAsUser()`, trait `CreatesSchedulerFixture`
  qui génère les classeurs Excel par code plutôt qu'un binaire versionné).
  238 tests écrits en parallèle sur quatre périmètres disjoints : scheduler (50),
  bulk et import Excel (56), écran et slides (48), CRUD et administration (84).
  Suite complète : 253 passés, 7 ignorés (2FA), 0 échec.
- 2026-08-18 — quatre fichiers de tests du starter kit supprimés : ils couvraient
  l'inscription, la réinitialisation de mot de passe et la vérification d'email,
  toutes désactivées dans `config/fortify.php`. `DashboardTest` et `AuthenticationTest`
  ont été réorientés vers `/scheduler`, le vrai point d'entrée après connexion.
