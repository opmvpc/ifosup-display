# Audit — validation des formulaires et affichage des erreurs

Date : 2026-08-20
Périmètre : toutes les `FormRequests` de `app/Http/Requests/`, les `$request->validate()` en ligne
(`ScheduleController`, `ScreenSlideController`, `SchedulerImportController`), et le rendu des erreurs
côté `resources/js/`. Vérifications faites par lecture de code, par `php artisan tinker` dans le
conteneur `docker-compose.dev.yml`, et par relecture des 253 tests existants (`tests/Feature/`).

## Vue d'ensemble

Le point le plus grave de cet audit n'est **pas** une règle de validation manquante mais une
**absence totale du dossier `lang/`** dans le projet (confirmé par `find /app/lang` dans le
conteneur : `No such file or directory`). Conséquence vérifiée en conditions réelles via
`php artisan tinker` :

- Tout champ dont la `FormRequest` (ou le `validate()` en ligne) **n'a pas** de méthode `messages()`
  affiche, au lieu d'un texte lisible, la clé de traduction brute : `validation.required`,
  `validation.min.string`, `validation.current_password`, `validation.exists`, etc.
- Le message générique du 422 (`$exception->getMessage()`) n'est pas non plus "The given data was
  invalid." : Laravel prend le premier message de champ et ajoute `(and N more errors)`. Résultat
  observé en tinker sur un formulaire **avec** messages personnalisés mais incomplets :
  `"Le nom est requis. (and 1 more error)"` — français et anglais mélangés dans la même phrase.

Ce mécanisme traverse ensuite deux couches distinctes côté frontend (Volet B) : les formulaires
Inertia classiques (qui lisent `errors.<champ>` — donc affichent la clé brute directement sous le
champ) et les appels `fetch()` manuels de `Scheduler.vue` / `ScreenSlides.vue` / `SchedulerImport.vue`
(qui ne lisent que le `message` global résumé, donc perdent en plus le détail par champ).

En dehors de ce problème transverse, les règles métier elles-mêmes sont globalement correctes et
bien couvertes par les 253 tests existants (`unique`, `exists`, `date_format`, `in:`, `max` sur les
uploads sont presents partout où on les attend). Le bug `end_week`/`gte` déjà repéré est confirmé,
mais il est déjà documenté et testé (voir plus bas) : il ne fait pas planter l'application.

## Volet A — règles manquantes ou inopérantes

| Formulaire | Champ | Problème | Règle proposée |
|---|---|---|---|
| `ScheduleController::bulkPreview` (inline) | `end_week` | `gte:start_week` compare `mb_strlen()` sur deux chaînes non numériques (`YYYY-Www` fait toujours 8 caractères) : la règle **ne rejette jamais rien**. Confirmé par `tests/Feature/Scheduler/BulkPreviewTest.php` (test dédié, qui documente le bug et vérifie que la requête réussit avec une liste de dates vide). 🟠 déjà mitigé par le comportement défensif du contrôleur (retourne `[]`), mais aucun message n'explique à l'utilisateur que sa plage est inversée. | Remplacer par une règle `after_or_equal` calculée sur les dates réelles (lundi de semaine ISO), ou une règle custom qui compare `isoWeekToMonday(end) >= isoWeekToMonday(start)`. |
| `Room`/`Teacher`/`Group` Store/Update | `name` | Aucune contrainte `unique` en base (migrations `rooms`, `teachers`, `groups` : simple `string('name')`) ni dans la `FormRequest`. Deux locaux, enseignants ou sections peuvent porter le même nom sans avertissement. 🟠 | `unique:rooms,name` (ignorer l'id courant sur Update), idem teachers/groups. |
| `SchedulerImportController::upload` | — | Pas de méthode `messages()` : tout échec (`mimes`, `max`, `min`/`max` sur `start_year`) affiche la clé brute sous le champ via `<InputError>` (formulaire Inertia classique, vérifié). 🔴 | Ajouter `messages()`. |
| `SchedulerImportController::executeImport` | `selected_rooms`, `selected_courses` | Pas de `messages()`, et en plus le frontend attend une clé `error` alors que Laravel renvoie `message`/`errors` sur un 422 de validation (voir Volet B). 🔴 | Ajouter `messages()` et corriger la clé lue côté front. |
| `ScreenSlideController::reorder` | `slide_ids` | Pas de `messages()` (contrairement à `store`/`update` qui en ont). 🟠 | Ajouter `messages()` par cohérence avec le reste du contrôleur. |
| `ScheduleIndexRequest` | `from`, `to` | Pas de `messages()`. Impact limité (champs `nullable`, erreur peu probable en usage normal) mais présent. 🟢 | Ajouter `messages()` ou tolérer vu le faible risque. |
| `StoreScheduleAssignmentRequest` / `UpdateScheduleAssignmentRequest` / `UpdateScheduleAssignmentStatusRequest` | tous les champs | Pas de `messages()`. Utilisées par les `fetch()` de `Scheduler.vue` (drag & drop, création, changement de statut) : un champ invalide affiche `validation.required (and N more errors)` dans une `window.alert()`. 🔴 | Ajouter `messages()`. |
| `Settings/PasswordUpdateRequest`, `ProfileDeleteRequest`, `ProfileUpdateRequest` | `current_password`, `password`, `name`, `email` | Pas de `messages()`. Formulaire Inertia classique (`<InputError>` par champ) : un mot de passe actuel faux affiche littéralement `validation.current_password` sous le champ (vérifié en tinker). 🔴 | Ajouter `messages()`, ou — mieux — publier `lang/fr/validation.php` (voir section langue) qui corrige tous ces cas d'un coup. |
| `StoreCourseRequest` / `UpdateCourseRequest` | `teacher_id` | `nullable|exists:teachers,id` : un cours peut n'avoir aucun enseignant. Si c'est voulu métier, aucune action ; sinon manque un `required`. 🟢 (à confirmer avec le métier) | — |
| `ScreenSlideController::store/update` | `duration` | Bornes `min:1000`/`max:120000` cohérentes ; rien à signaler. | — |

Aucune fuite de mass-assignment constatée : tous les contrôleurs appellent `Model::create()`/`update()`
avec `$request->validated()` (jamais `$request->all()`), et les champs calculés côté serveur
(`position`, `is_locked`, `status` par défaut) ne viennent jamais de l'entrée utilisateur.

## Volet A — messages et langue

- `.env.example` : `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=fr`. Mais **aucun fichier `lang/` n'existe**
  dans le dépôt ni dans l'image Docker (`lang:publish` n'a jamais été lancé). Résultat : la locale
  `fr` déclarée n'a aucun effet sur les messages de validation par défaut de Laravel, et il n'y a
  même pas de repli anglais lisible — juste les clés brutes (`validation.required`, `validation.max.string`,
  `validation.exists`, `validation.confirmed`, `validation.current_password`, `validation.mimes`…).
  🔴 C'est la cause racine de la majorité des points listés ci-dessus et dans le Volet B.
- **FormRequests avec `messages()` (français, correct)** : `Admin/StoreUserRequest`,
  `Admin/UpdateUserRequest`, `StoreCourseRequest`, `UpdateCourseRequest`, `StoreGroupRequest`,
  `UpdateGroupRequest`, `StoreRoomRequest`, `UpdateRoomRequest`, `StoreTeacherRequest`,
  `UpdateTeacherRequest`. Les `validate()` en ligne de `ScreenSlideController::store` et `::update`
  (image/vidéo/welcome) en ont aussi.
- **FormRequests sans `messages()` (clé brute exposée)** : `ScheduleIndexRequest`,
  `StoreScheduleAssignmentRequest`, `UpdateScheduleAssignmentRequest`,
  `UpdateScheduleAssignmentStatusRequest`, `Settings/PasswordUpdateRequest`,
  `Settings/ProfileDeleteRequest`, `Settings/ProfileUpdateRequest` (et son trait
  `App\Concerns\ProfileValidationRules`), `Settings/TwoFactorAuthenticationRequest` (rules vides,
  sans impact). Idem pour les `validate()` en ligne de `ScheduleController::bulkPreview`,
  `::bulkStore`, `ScreenSlideController::reorder`, `SchedulerImportController::upload` et
  `::executeImport`.
- **Mélange de langue déjà présent sans même parler du bug ci-dessus** : `resources/js/pages/settings/Password.vue`
  et `Profile.vue` sont intégralement en anglais ("Update password", "Current password", "Save",
  "Name", "Email address"...) alors que tout le reste de l'interface (cours, locaux, enseignants,
  sections, utilisateurs admin, planning) est en français. Ce sont les pages scaffoldées par
  Laravel Fortify/Breeze, jamais traduites par l'étudiant. 🟠
- Effet cumulé vérifié par `tinker` : sur un même formulaire, si le premier champ en échec a un
  message personnalisé français mais qu'un second champ non couvert échoue aussi, le résumé devient
  `"Le nom est requis. (and 1 more error)"` — phrase bilingue dans un seul message. Ce résumé est
  précisément ce que lisent les `fetch()` manuels (Volet B).

## Volet B — erreurs non affichées

- **Formulaires Inertia classiques** (`resources/courses`, `groups`, `rooms`, `teachers`,
  `admin/users`, `SchedulerImport.vue` pour l'upload) : chaque champ a bien son `<InputError>`
  associé — aucun champ orphelin trouvé. Le problème n'est donc pas "l'erreur n'atteint jamais
  l'utilisateur" mais "l'erreur qui l'atteint est illisible" quand la `FormRequest` manque de
  `messages()` (cf. Volet A). C'est le cas concret et vérifié de `SchedulerImport.vue` : un fichier
  trop lourd ou du mauvais type produit bien un `<InputError :message="uploadForm.errors.file">`
  affiché... contenant `validation.mimes` ou `validation.max.file`. 🔴 (répond directement à la
  question "un fichier trop lourd produit-il un message compréhensible ?" — non.)
- **Erreurs globales / non liées à un champ** : `AlertError.vue` existe mais n'est utilisé que dans
  `TwoFactorRecoveryCodes.vue` et `TwoFactorSetupModal.vue`. Aucun des CRUD ressources, ni
  `Scheduler.vue`, ni `ScreenSlides.vue`, ni `SchedulerImport.vue` ne l'utilise pour une erreur
  serveur générique (500, panne réseau) — ces cas retombent sur `window.alert()` ou un texte brut
  dans un `<p>`, jamais sur un composant d'alerte cohérent avec le design system. 🟢 confort, pas
  bloquant.
- Les FormRequests **avec** `messages()` complets fonctionnent correctement de bout en bout côté
  Inertia (vérifié sur Course/Room/Teacher/Group/Admin User) : la bonne clé de champ est peuplée,
  le bon texte français s'affiche sous le bon champ.

## Volet B — appels fetch et réponses 422

Trois fichiers font des `fetch()` manuels hors du flux Inertia standard : `Scheduler.vue` (~2000
lignes), `ScreenSlides.vue`, `SchedulerImport.vue`. Les trois partagent le même défaut :

- Un helper (`parseResponseError` / `apiPost`) lit **uniquement** `payload.message` (ou `payload.error`
  pour `SchedulerImport.vue`) sur une réponse non-OK, puis lève une `Error` avec ce seul texte.
  **L'objet `errors` par champ que Laravel place dans tout 422 de validation est systématiquement
  ignoré.** Le détail (quel champ, quelle règle) n'est donc jamais montré : l'utilisateur ne voit
  qu'une phrase unique, via `window.alert()` (déplacement/création/suppression/statut dans
  `Scheduler.vue`, suppression/réordonnancement dans `ScreenSlides.vue`) ou un bandeau `formError`
  générique (création/édition de slide). 🔴
- Combiné au problème de langue du Volet A : cette phrase unique est soit une clé brute
  (`validation.required (and 3 more errors)`) pour les endpoints sans `messages()`
  (`StoreScheduleAssignmentRequest`, `UpdateScheduleAssignmentRequest`,
  `UpdateScheduleAssignmentStatusRequest`, `ScreenSlideController::reorder`), soit une phrase
  française tronquée avec un suffixe anglais pour les endpoints qui ont des `messages()` partiels.
  Aucun cas n'affiche un message entièrement propre en cas d'erreurs multiples.
- **Incohérence de clé JSON dans `SchedulerImportController` / `SchedulerImport.vue`** : les 422
  "métier" (`'Aucun fichier en attente.'`) renvoient `{"error": "..."}`, ce que `apiPost()` lit
  correctement. Mais `executeImport()` valide aussi `selected_rooms`/`selected_courses` via
  `$request->validate([...])`, qui — en cas d'échec — renvoie le format standard Laravel
  `{"message": "...", "errors": {...}}`, **sans** clé `error`. Résultat : `json.error` vaut
  `undefined`, et `apiPost` retombe sur le fallback générique `'Une erreur est survenue.'` — la
  cause réelle disparaît complètement, y compris la clé brute qui aurait au moins donné un indice.
  🔴
- Les 422 "métier" volontaires (créneau déjà occupé, slide de bienvenue non déplaçable, etc.) sont
  eux correctement écrits en français directement dans le contrôleur (`response()->json(['message'
  => '...'], 422)`) et s'affichent bien : ce sont les seuls messages fiables de bout en bout dans
  ces trois fichiers.

## Recommandations classées

🔴 à corriger avant la production
1. Publier un `lang/fr` (au minimum `lang/fr/validation.php`, via `php artisan lang:publish` puis
   traduction) pour que les FormRequests sans `messages()` cessent d'afficher des clés brutes
   (`validation.required`, `validation.current_password`, etc.) — impacte directement les pages
   Settings (mot de passe, profil) et le planning (`Scheduler.vue`).
2. Ajouter `messages()` à `StoreScheduleAssignmentRequest`, `UpdateScheduleAssignmentRequest`,
   `UpdateScheduleAssignmentStatusRequest`, `SchedulerImportController::upload`,
   `SchedulerImportController::executeImport`.
3. Faire lire aux trois helpers `fetch()` (`Scheduler.vue`, `ScreenSlides.vue`,
   `SchedulerImport.vue`) l'objet `errors` du 422 (pas seulement `message`/`error`), et afficher au
   moins la liste des erreurs de champ plutôt qu'une phrase résumée.
4. Corriger l'incohérence `error` vs `message` dans `SchedulerImportController::executeImport` /
   `SchedulerImport.vue::apiPost` pour que les erreurs de validation de cet endpoint cessent d'être
   totalement silencieuses.
5. Corriger la règle `gte:start_week` de `ScheduleController::bulkPreview` (comparaison de chaînes
   au lieu de dates) pour qu'elle rejette réellement une plage inversée, avec un message explicite,
   plutôt que de dépendre du comportement défensif actuel (liste vide sans explication).

🟠 souhaitable
6. Ajouter `unique` sur `name` pour `rooms`, `teachers`, `groups` (migration + `FormRequest`) pour
   éviter les doublons silencieux.
7. Ajouter `messages()` à `ScreenSlideController::reorder` par cohérence avec `store`/`update`.
8. Traduire `resources/js/pages/settings/Password.vue` et `Profile.vue` en français pour la
   cohérence globale de l'interface.

🟢 confort
9. Utiliser `AlertError.vue` (déjà présent, déjà stylé) pour les erreurs globales/réseau dans les
   CRUD ressources, `Scheduler.vue`, `ScreenSlides.vue`, `SchedulerImport.vue`, au lieu de
   `window.alert()`.
10. Ajouter `messages()` à `ScheduleIndexRequest` (risque faible, champs `nullable`).
11. Clarifier si `teacher_id` doit rester optionnel sur les cours (vérifier avec le métier).
