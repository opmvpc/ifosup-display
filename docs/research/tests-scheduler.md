# Plan de tests — Module Scheduler / Assignments

Périmètre analysé : `ScheduleController`, les 4 `FormRequest` associées, les modèles
`Assignment`, `Course`, `Room`, `Teacher`, `Group`, les migrations et factories liées,
et les routes `scheduler*` de `routes/web.php`. Le module d'import Excel
(`SchedulerSheetParser`, `SchedulerImportController`) est explicitement hors périmètre.

## Ce que fait le module

Le contrôleur `App\Http\Controllers\ScheduleController` expose 7 actions, toutes
protégées par le middleware `auth` + `verified` (groupe dans `routes/web.php`), sans
policy ni scoping par utilisateur (n'importe quel utilisateur connecté et vérifié peut
créer/modifier/supprimer n'importe quelle attribution) :

- `index` (GET `scheduler`, route `schedule`) — page Inertia listant les attributions
  sur une plage `[from, to]`, avec repli sur des cookies (`scheduler_from_date`,
  `scheduler_to_date`) si les query params sont absents, et écriture systématique de
  ces cookies (durée 120 min, `SameSite=Lax`, `httpOnly=false`).
- `store` (POST `scheduler/assignments`) — crée une attribution après vérification
  qu'aucune attribution n'existe déjà sur le même triplet (salle, date, période).
- `update` (PATCH `scheduler/assignments/{assignment}`) — modifie salle/date/période
  d'une attribution existante (le cours et le statut ne sont **pas** modifiables par
  cette route), avec la même vérification d'occupation (en s'excluant elle-même).
- `updateStatus` (PATCH `scheduler/assignments/{assignment}/status`) — change
  uniquement le statut (`planned`/`cancelled`/`late`), sans aucune vérification
  d'occupation.
- `destroy` (DELETE `scheduler/assignments/{assignment}`) — suppression pure, sans
  vérification.
- `bulkPreview` (POST `scheduler/assignments/bulk/preview`) — à partir d'un cours, une
  salle, un jour de semaine ISO, une période et une plage de semaines ISO
  (`YYYY-Www`), calcule la liste des dates concernées et retourne les attributions déjà
  existantes sur ces dates pour la période donnée.
- `bulkStore` (POST `scheduler/assignments/bulk`) — pour une liste de lignes
  `{date, room_id}` et un `course_id`/`period` communs, **supprime** toute attribution
  existante sur chaque triplet (salle, date, période) puis recrée une attribution
  `status = planned`.

## Règles métier et cas limites

1. **Occupation d'un créneau** (`slotIsOccupied`) : unicité logique sur
   (`room_id`, `date`, `period`) — vérifiée par `store`/`update`, ignorée par
   `updateStatus`. La vérification ne tient **pas compte du `status`** de
   l'attribution existante (voir bug n°1 ci-dessous).
2. **`update` ne permet pas de changer `course_id` ni `status`** — seuls `room_id`,
   `date`, `period` sont dans les règles de validation ; un `course_id` envoyé dans le
   payload est silencieusement ignoré (`$request->validated()` ne retient que les
   champs déclarés).
3. **`store` accepte un `status` optionnel** (`sometimes|in:planned,cancelled,late`) :
   il est donc possible de créer directement une attribution `cancelled` ou `late`,
   contournant le flux normal (créer en `planned` puis `updateStatus`). Si `status`
   est omis, la colonne DB retombe sur son défaut `planned`.
4. **`index` — normalisation des dates** : `from`/`to` sont validés `date_format:Y-m-d`
   s'ils viennent en query string (422 si mal formés), mais **pas si la valeur vient
   du cookie** : `normalizeDate()` retombe silencieusement sur la date par défaut
   (`now()-1j` / `now()+30j`) si le cookie est vide, absent ou invalide (`try/catch`
   autour de `Carbon::createFromFormat`).
5. **`index` — inversion automatique** : si `from > to` après normalisation, les deux
   bornes sont échangées silencieusement (pas d'erreur utilisateur).
6. **`index` ne filtre pas par statut** : les attributions `cancelled` et `late`
   apparaissent dans la réponse au même titre que `planned`.
7. **`bulkPreview` — calcul des dates** : utilise le vrai calcul ISO 8601 (lundi de la
   semaine 1 = lundi contenant/précédant le 4 janvier). La comparaison
   `end_week gte start_week` est une comparaison **de chaînes** (règle Laravel `gte`
   sans coercition date) ; elle reste correcte car le format `YYYY-Www` est
   lexicographiquement ordonné (`2025-W50` < `2026-W01`), mais c'est un point fragile
   à documenter par un test plutôt qu'à supposer.
8. **`bulkPreview` — `existing` n'est pas filtré par `room_id`** malgré que `room_id`
   soit un champ requis et validé : la requête ne fait que
   `whereIn('date', $dates)->where('period', ...)`, donc les attributions retournées
   couvrent **toutes les salles**, pas seulement celle demandée (voir bug n°2).
9. **`bulkStore` écrase silencieusement** toute attribution déjà présente sur le
   triplet (salle, date, période), y compris si elle appartient à un **autre cours**
   ou a un statut différent : delete puis insert, sans avertissement, contrairement à
   `store`/`update` qui bloquent avec 422. C'est une divergence de comportement
   volontaire (mode "import en masse") mais qui mérite d'être verrouillée par un test
   de régression.
10. **`bulkStore` — doublons de lignes** : si `rows` contient deux fois le même
    couple (`room_id`, `date`), chaque itération supprime ce qui vient d'être créé à
    l'itération précédente puis recrée — le compteur `inserted` retourné (
    `count($data['rows'])`) ne correspond alors pas au nombre réel d'enregistrements
    survivants en base.
11. **`room_id` nullable en base** (migration `assignments`) mais **requis** dans
    `StoreScheduleAssignmentRequest`/`UpdateScheduleAssignmentRequest` — impossible de
    créer/mettre à jour une attribution sans salle via ces routes, alors que le schéma
    et la factory (90 % de chance de salle) le permettent.
12. **`course_id` nullable dans `courses.teacher_id`** (migration
    `make_teacher_id_nullable_in_courses_table`) — un cours sans enseignant est un
    état valide, à couvrir si des tests touchent l'affichage groupé par enseignant
    (probablement plutôt le périmètre `CourseController`, mais utile de le savoir pour
    les factories partagées).
13. **`Assignment::$with = ['course', 'room']`** — l'eager loading est systématique ;
    un test peut vérifier l'absence de requêtes N+1 implicites mais ce n'est pas
    critique pour ce périmètre.
14. **Aucune policy / scoping utilisateur** — tout utilisateur `auth`+`verified` peut
    agir sur n'importe quelle attribution. Un test peut simplement vérifier qu'un
    utilisateur non authentifié est redirigé (302) sur chacune des 7 routes.

## Bugs ou points douteux repérés

1. **Occupation qui ignore le statut** (`slotIsOccupied`, utilisé par `store` et
   `update`) : une attribution `cancelled` (ou `late`) sur un créneau continue de le
   "bloquer" pour une nouvelle création/déplacement, alors qu'intuitivement un cours
   annulé devrait libérer la salle. À confirmer avec l'équipe métier, mais en
   attendant c'est un comportement testable et potentiellement une régression si
   quelqu'un "corrige" ça sans s'en rendre compte.
2. **`bulkPreview` ignore `room_id` dans la requête `existing`** alors que le champ est
   validé comme obligatoire — soit le paramètre est mort (jamais utilisé après
   validation), soit c'est un oubli de `->where('room_id', $data['room_id'])`. Le
   frontend reçoit donc des conflits potentiels sur des salles non concernées.
3. **Incohérence store/update vs bulkStore** sur la gestion des conflits (bloquant vs
   écrasant) — comportement probablement voulu mais non documenté dans le code ; à
   figer par des tests pour éviter qu'une des deux logiques ne dérive silencieusement
   vers l'autre.
4. **`bulkStore` ne vérifie pas que `course_id` correspond à un cours actif/existant
   au moment de la boucle** (seulement en amont via la validation) — non exploitable
   en race condition dans un test synchrone classique, mentionné pour mémoire.
5. **Compteur `inserted` trompeur en cas de doublons dans `rows`** (voir règle n°10).
6. **Cookies `scheduler_from_date`/`scheduler_to_date` en `httpOnly=false`** — pas un
   bug fonctionnel, mais une décision de sécurité discutable (lisible en JS) ; hors
   scope des tests métier mais à signaler.
7. **`store` permet de créer directement un statut `cancelled`/`late`** — comportement
   sans doute volontaire (réutilisé par l'import), mais à verrouiller par un test
   explicite pour qu'une future contrainte "création toujours en planned" ne casse pas
   silencieusement l'import.

## Plan de tests

### `tests/Feature/Scheduler/ScheduleIndexTest.php`

| # | Nom du test | Vérifie | Données | Assertion clé |
|---|---|---|---|---|
| 1 | `it('redirige un visiteur non authentifié vers la connexion')` | middleware auth | aucun utilisateur connecté | `assertRedirect(route('login'))` |
| 2 | `it('affiche la page scheduler pour un utilisateur connecté')` | rendu Inertia de base | `User::factory()->create()` | `assertOk()` + `assertInertia(fn($page)=>$page->component('Schedule'))` |
| 3 | `it('utilise les dates par défaut quand aucun paramètre ni cookie ne sont fournis')` | defaults (`now()-1j` / `now()+30j`) | aucun query param, aucun cookie | props `fromDate`/`toDate` égaux aux valeurs attendues |
| 4 | `it('utilise les paramètres from et to fournis en query string')` | lecture des query params | `?from=2026-09-01&to=2026-09-10` | props `fromDate`/`toDate` = valeurs fournies |
| 5 | `it('rejette un paramètre from mal formé avec une erreur de validation')` | `ScheduleIndexRequest` | `?from=not-a-date` | `assertStatus(302)` ou `assertSessionHasErrors('from')` (selon redirection Inertia) |
| 6 | `it('inverse silencieusement from et to si from est postérieur à to')` | swap logic | `?from=2026-09-20&to=2026-09-01` | props `fromDate`=2026-09-01, `toDate`=2026-09-20 |
| 7 | `it('retombe sur les cookies quand les query params sont absents')` | fallback cookie | cookie `scheduler_from_date=2026-09-05` posé, pas de query | prop `fromDate`=2026-09-05 |
| 8 | `it('ignore un cookie corrompu et retombe sur la valeur par défaut')` | `normalizeDate` try/catch | cookie `scheduler_from_date=n'importe-quoi` | prop `fromDate` = défaut (`now()-1j`) |
| 9 | `it('écrit les cookies scheduler_from_date et scheduler_to_date après la requête')` | `Cookie::queue` | requête simple avec `?from=&to=` valides | `assertPlainCookie('scheduler_from_date', ...)` (ou vérifier header Set-Cookie) |
| 10 | `it('inclut les attributions planned cancelled et late sans filtrage par statut')` | absence de filtre statut | 3 assignments (`planned`,`cancelled`,`late`) dans la plage | prop `assignments` contient les 3 |
| 11 | `it('exclut les attributions hors de la plage from/to')` | filtre `whereDate` | assignment avant `from` et un après `to` | prop `assignments` ne les contient pas |
| 12 | `it('retourne toutes les salles et tous les cours triés par code')` | `Room::all()`, `Course::orderBy('code')` | plusieurs `Course` avec codes désordonnés | prop `courses` triée par `code` croissant |

### `tests/Feature/Scheduler/StoreAssignmentTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 13 | `it('crée une attribution valide et retourne 201')` | happy path | course+room existants | `assertCreated()`, en base `assignments` +1 |
| 14 | `it('crée une attribution avec le statut planned par défaut si status est omis')` | défaut DB | payload sans `status` | `assertJsonPath('assignment.status', 'planned')` |
| 15 | `it('accepte un statut cancelled ou late explicite à la création')` | règle n°3 | `status: 'cancelled'` | `assertJsonPath('assignment.status', 'cancelled')` |
| 16 | `it('refuse une attribution sur un local déjà occupé au même créneau planned')` | `slotIsOccupied` | assignment existant même room/date/period (`planned`) | `assertStatus(422)`, message `'Ce créneau est déjà occupé.'` |
| 17 | `it('refuse une attribution sur un créneau occupé par une attribution cancelled')` | bug n°1, comportement actuel | assignment existant `cancelled` même triplet | `assertStatus(422)` (documente le comportement actuel) |
| 18 | `it('autorise une attribution sur la même salle et date mais une période différente')` | granularité période | assignment existant period=morning, nouveau period=afternoon même salle/date | `assertCreated()` |
| 19 | `it('autorise une attribution sur le même créneau mais une salle différente')` | granularité salle | même date/period, room différente | `assertCreated()` |
| 20 | `it('refuse la création sans course_id')` | validation | payload sans `course_id` | `assertStatus(422)`, erreur sur `course_id` |
| 21 | `it('refuse la création avec un course_id inexistant')` | `exists:courses,id` | `course_id: 999999` | erreur validation |
| 22 | `it('refuse la création sans room_id')` | validation | payload sans `room_id` | erreur validation |
| 23 | `it('refuse la création avec un room_id inexistant')` | `exists:rooms,id` | `room_id: 999999` | erreur validation |
| 24 | `it('refuse une date mal formée')` | `date_format:Y-m-d` | `date: '01/09/2026'` | erreur validation |
| 25 | `it('refuse une date calendaire invalide comme 2026-02-30')` | edge case format | `date: '2026-02-30'` | erreur validation (à confirmer empiriquement, sinon documenter le comportement réel) |
| 26 | `it('refuse une période invalide')` | `in:morning,afternoon,evening` | `period: 'night'` | erreur validation |
| 27 | `it('refuse un statut invalide à la création')` | `in:planned,cancelled,late` | `status: 'done'` | erreur validation |
| 28 | `it('redirige un utilisateur non authentifié qui tente de créer une attribution')` | middleware | pas de login | 302 vers login |
| 29 | `it('retourne l'attribution créée avec ses relations course et room chargées')` | `->load(['course','room'])` | payload valide | JSON contient `assignment.course.id` et `assignment.room.id` |

### `tests/Feature/Scheduler/UpdateAssignmentTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 30 | `it('met à jour la salle la date et la période d'une attribution')` | happy path | assignment existant, nouveau room/date/period libres | `assertOk()`, en base les champs sont modifiés |
| 31 | `it('ignore un course_id envoyé dans le payload de mise à jour')` | règle n°2 | payload avec `course_id` différent | en base `course_id` inchangé |
| 32 | `it('ignore un status envoyé dans le payload de mise à jour')` | règle n°2 | payload avec `status: 'cancelled'` | en base `status` inchangé |
| 33 | `it('autorise de renvoyer exactement le même créneau (no-op)')` | exclusion de soi-même dans `slotIsOccupied` | payload identique aux valeurs actuelles | `assertOk()`, pas de 422 |
| 34 | `it('refuse de déplacer une attribution vers un créneau déjà occupé par une autre')` | conflit | 2 assignments, on tente de déplacer B sur le créneau de A | `assertStatus(422)` |
| 35 | `it('refuse de déplacer une attribution vers un créneau occupé par une attribution cancelled')` | bug n°1 sur update | assignment cible existante en `cancelled` | `assertStatus(422)` (documente le comportement) |
| 36 | `it('retourne 404 pour une attribution inexistante')` | route model binding | `PATCH scheduler/assignments/999999` | `assertNotFound()` |
| 37 | `it('refuse la mise à jour sans room_id/date/period')` | validation | payload incomplet | erreur validation |
| 38 | `it('redirige un utilisateur non authentifié qui tente de modifier une attribution')` | middleware | pas de login | 302 |

### `tests/Feature/Scheduler/UpdateAssignmentStatusTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 39 | `it('change le statut vers cancelled sans vérifier l'occupation du créneau')` | pas de `slotIsOccupied` ici | assignment existant | `assertOk()`, `status` = cancelled en base |
| 40 | `it('change le statut vers late')` | idem | — | statut mis à jour |
| 41 | `it('remet le statut à planned')` | idem | assignment `cancelled` en base | statut = planned |
| 42 | `it('refuse un statut invalide')` | validation `in:planned,cancelled,late` | `status: 'unknown'` | 422 |
| 43 | `it('retourne 404 pour une attribution inexistante')` | route model binding | id inexistant | `assertNotFound()` |
| 44 | `it('ne modifie ni la salle ni la date ni le cours')` | isolation du champ | payload `status` seul | en base room/date/period/course_id inchangés |

### `tests/Feature/Scheduler/DestroyAssignmentTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 45 | `it('supprime une attribution existante')` | happy path | assignment existant | `assertOk()`, `assertJson(['deleted'=>true])`, `assertDatabaseMissing` |
| 46 | `it('retourne 404 en tentant de supprimer une attribution inexistante')` | route model binding | id inexistant | `assertNotFound()` |
| 47 | `it('redirige un utilisateur non authentifié qui tente de supprimer')` | middleware | pas de login | 302 |

### `tests/Feature/Scheduler/BulkPreviewTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 48 | `it('calcule les dates hebdomadaires entre start_week et end_week pour le jour donné')` | `isoWeekToMonday` + boucle | `day_of_week=3` (mercredi), `start_week=2026-W02`, `end_week=2026-W04` | `dates` = les 3 mercredis attendus, valeurs exactes vérifiées |
| 49 | `it('retourne une seule date quand start_week et end_week sont la même semaine')` | edge case | `start_week=end_week=2026-W10` | `count(dates) === 1` |
| 50 | `it('gère correctement une plage à cheval sur deux années civiles')` | robustesse ISO | `start_week=2025-W52`, `end_week=2026-W02` | dates cohérentes chronologiquement (pas de saut/désordre) |
| 51 | `it('refuse end_week antérieure à start_week')` | règle `gte` | `start_week=2026-W10`, `end_week=2026-W05` | 422 |
| 52 | `it('refuse un day_of_week hors de 1 à 7')` | validation | `day_of_week: 8` | 422 |
| 53 | `it('refuse un format de semaine ISO invalide')` | regex | `start_week: '2026-13'` | 422 |
| 54 | `it('retourne les attributions existantes sur les dates et la période calculées')` | jointure `existing` | assignment déjà présent sur une des dates générées, même période | `existing` contient cette attribution avec `course` chargé |
| 55 | `it('inclut dans existing des attributions sur une AUTRE salle que celle demandée')` | bug n°2 — documente le comportement actuel | assignment sur `room_id` différent de celui du payload, même date/période | `existing` contient quand même cette ligne (à re-vérifier après correctif éventuel) |
| 56 | `it('exclut de existing les attributions sur une autre période')` | filtre period correct | assignment même date, période différente | absent de `existing` |
| 57 | `it('refuse un course_id ou room_id inexistant')` | validation | ids invalides | 422 |

### `tests/Feature/Scheduler/BulkStoreTest.php`

| # | Nom | Vérifie | Données | Assertion |
|---|---|---|---|---|
| 58 | `it('crée une attribution planned par ligne fournie')` | happy path | 3 rows valides | `assertJson(['inserted'=>3])`, 3 lignes en base avec `status=planned` |
| 59 | `it('écrase une attribution existante sur le même créneau lors d'un bulkStore')` | règle n°9 | assignment pré-existant (autre cours) sur le triplet room/date/period ciblé | l'ancienne ligne n'existe plus, la nouvelle porte le `course_id` du bulk |
| 60 | `it('écrase une attribution cancelled ou late lors d'un bulkStore')` | cohérence avec règle n°9 | assignment pré-existant `late` sur le créneau | remplacée par une ligne `planned` |
| 61 | `it('ne touche pas les attributions sur une autre période ou une autre salle')` | portée du delete | assignment sur period différente au même room/date | reste inchangée en base |
| 62 | `it('applique la mise à jour dans une transaction : aucune ligne créée si une ligne est invalide')` | `DB::transaction` + validation amont | payload avec une `rows.*.room_id` invalide | 422, aucune attribution créée (vérifier absence de tout effet de bord) |
| 63 | `it('gère un doublon de ligne (même room_id/date) en ne conservant qu'une attribution')` | bug n°5 | `rows` avec 2 fois le même couple room/date | 1 seule ligne en base pour ce créneau alors que `inserted` vaut 2 (documente l'écart) |
| 64 | `it('refuse un payload sans rows ou avec rows vide')` | validation `min:1` | `rows: []` | 422 |
| 65 | `it('refuse une ligne avec une date mal formée')` | validation | `rows.0.date: '2026/09/01'` | 422 |
| 66 | `it('redirige un utilisateur non authentifié qui tente un bulkStore')` | middleware | pas de login | 302 |

### Optionnel — `tests/Unit/AssignmentModelTest.php`

| # | Nom | Vérifie | Assertion |
|---|---|---|---|
| 67 | `it('caste la colonne date au format Y-m-d')` | cast `date:Y-m-d` | `$assignment->date->toDateString()` correspond, et sérialisation JSON = string `Y-m-d` |
| 68 | `it('charge automatiquement course et room via $with')` | eager loading par défaut | `Assignment::find($id)->relationLoaded('course')` est vrai sans appel explicite à `load()` |

**Total proposé : 68 tests** répartis sur 7 fichiers Feature + 1 fichier Unit optionnel.

## Factories et fixtures à créer

L'`AssignmentFactory`, `CourseFactory`, `RoomFactory`, `TeacherFactory` existent déjà et
couvrent le cas générique. Pour écrire les tests ci-dessus proprement (sans répéter des
`->state([...])` ad hoc partout), il manque :

1. **`AssignmentFactory` — states nommés** (aucun n'existe actuellement, seul l'état par
   défaut) :
   - `planned()`, `cancelled()`, `late()` → `state(fn () => ['status' => 'planned'|'cancelled'|'late'])`.
   - `onDate(string|Carbon $date)` → fixe `date`.
   - `inRoom(Room|int $room)` → fixe `room_id`.
   - `inPeriod(string $period)` → fixe `period`.
   - `withoutRoom()` → `state(fn () => ['room_id' => null])` (existe déjà de façon
     probabiliste à 10 % via `fake()->boolean(90)`, mais un état explicite est
     nécessaire pour un test déterministe, même si en pratique `store`/`update`
     n'acceptent jamais `room_id` null — utile surtout pour les tests `index`/modèle).
   - Un raccourci `forSlot(Room $room, string $date, string $period)` combinant les
     trois précédents simplifierait beaucoup les tests de conflits (n°16-19, 34-35,
     55, 59-61).

2. **`CourseFactory`** : rien de bloquant, mais un état `withoutTeacher()`
   (`teacher_id => null`) serait utile si un test veut vérifier qu'un cours sans
   enseignant s'affiche correctement dans le scheduler (prop `courses` de `index`).

3. **Aucune factory manquante pour `Room`, `Teacher`, `User`** — les factories
   existantes suffisent telles quelles (le `UserFactory` crée déjà des utilisateurs
   vérifiés par défaut, compatible avec le middleware `verified`).

4. **Pas de seeder dédié nécessaire** : les tests Feature listés utilisent
   `RefreshDatabase` (déjà branché globalement dans `tests/Pest.php` pour tout le
   dossier `Feature`) et créent leurs données via factories au cas par cas ; un
   seeder de démo n'apporterait rien pour des tests unitaires/feature isolés.

5. **Aide facultative** : une fonction Pest partagée `authenticatedUser()` (dans
   `tests/Pest.php` ou un fichier `tests/Feature/Scheduler/Pest.php` scoping le
   dossier) du type `function actingAsVerifiedUser(): User { return tap(User::factory()->create(), fn ($u) => test()->actingAs($u)); }`
   éviterait de répéter `$this->actingAs(User::factory()->create())` dans les ~50 tests
   authentifiés du plan ci-dessus.
