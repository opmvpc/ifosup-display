---
id: IFO-011
titre: Messages de validation illisibles et erreurs 422 perdues
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-20
mis-à-jour: 2026-08-20
---

## Contexte

Audit d'origine : [`../research/audit-validation-et-erreurs.md`](../research/audit-validation-et-erreurs.md).

Trois défauts distincts, tous visibles par l'utilisateur final.

**1. Aucun fichier de langue.** Le projet tourne avec `APP_LOCALE=fr` mais n'avait pas
de dossier `lang/`, ni dans le dépôt ni dans l'image. Toute règle de validation sans
message personnalisé affichait sa clé brute. Constaté sur la stack :

| Formulaire | Ce que voyait l'utilisateur |
|---|---|
| Créer un local (`messages()` présente) | « Le nom de la salle est requis. » |
| Créer une attribution (pas de `messages()`) | **`validation.required`** |

Une douzaine de formulaires étaient concernés : tout le scheduler, les paramètres de
compte, l'import Excel, la réorganisation des slides. Un mot de passe erroné affichait
`validation.current_password` sous le champ.

**2. Erreurs 422 jetées.** `Scheduler.vue`, `ScreenSlides.vue` et `SchedulerImport.vue`
appellent l'API par `fetch()`, hors du flux Inertia. Le helper de `SchedulerImport.vue`
lisait `json.error`, une clé qui n'existe pas dans une réponse de validation Laravel
(`{ message, errors }`) : le détail par champ disparaissait et l'utilisateur voyait
« Une erreur est survenue. »

**3. `gte:start_week` inopérante.** Sur deux chaînes non numériques, Laravel compare
`mb_strlen()` et non l'ordre lexicographique. Un format `YYYY-Www` faisant toujours
8 caractères, la règle ne rejetait jamais rien : une plage de semaines inversée était
acceptée et renvoyait une liste de dates vide, sans explication.

## Critères d'acceptation

- [x] `lang/fr/` créé — validation, auth, passwords, pagination — sans dépendance ajoutée
- [x] Aucune clé manquante par rapport à `lang/en` (vérifié par comparaison des deux
      arborescences), `attributes` renseigné avec les champs du projet
- [x] `messages()` métier sur les FormRequests du scheduler et des paramètres
- [x] `POST /scheduler/assignments` avec un corps vide renvoie des phrases françaises
- [x] Les trois pages hors Inertia affichent le détail par champ d'une réponse 422
- [x] La plage de semaines est réellement validée, avec un message explicite
- [x] Le test qui documentait l'ancien comportement de `end_week` est remplacé
- [x] Pint, Prettier, ESLint, vue-tsc et les 261 tests passent

## Journal du ticket

- 2026-08-20 — création et réalisation. Le point 1 est le plus visible des trois : il ne
  s'agissait pas d'une subtilité mais de clés techniques affichées telles quelles dans
  l'interface.
- 2026-08-20 — `bulkStore` n'a pas eu besoin de la correction du point 3 : contrairement
  à `bulkPreview`, il reçoit des dates explicites dans `rows`, pas une plage de semaines.
