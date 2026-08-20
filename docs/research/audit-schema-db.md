# Audit du schéma de base de données — IFOSUP Display

Contexte : migration vers MySQL 8.4 en production. Audit effectué sur le schéma réel
(stack Docker `mysql:8.4`, `SHOW CREATE TABLE`) et sur `database/migrations/`,
`app/Models/`, `database/factories/`, ainsi qu'en lecture seule sur les contrôleurs
qui écrivent des données (`ScheduleController`, `SchedulerImportController`, CRUD
`RoomController`/`GroupController`/`TeacherController`/`CourseController`,
`ScreenSlideController`).

**Preuve de terrain** : la base de dev contient déjà, à ce jour, des doublons exacts :
- `rooms` : deux lignes `name = '106'` — id 12 (18 `assignments` rattachés) et id 13 (0 rattaché).
- `groups` : deux lignes `name = 'BAC Développeur d'applications'` — id 7 et id 8.
- `teachers` : deux lignes `name = 'Dupont Marie'` — id 4 et id 7.

Ce n'est pas une hypothèse théorique : le bug se produit déjà.

## Schéma actuel (tableau synthétique)

| Table | Colonnes clés | Unicité | FK | Nullable notable |
|---|---|---|---|---|
| `rooms` | id, name | **aucune** | — | — |
| `groups` | id, name | **aucune** | — | — |
| `teachers` | id, name | **aucune** | — | — |
| `courses` | id, code, name, teacher_id | `code` unique | `teacher_id → teachers.id` SET NULL | `teacher_id` nullable |
| `course_group` (pivot) | course_id, group_id | **aucune** (composite) | `course_id`, `group_id` CASCADE | — |
| `assignments` | id, course_id, room_id, date, period(enum), status(enum) | **aucune** (composite date+period+room_id) | `course_id` CASCADE, `room_id` CASCADE | `room_id` nullable |
| `screen_slides` | id, type(enum), position, is_locked | aucune sur `position` | — | — |
| `users` | id, email | `email` unique | — | — |

Index existants : `assignments(date, room_id)`, `assignments(status)`, `screen_slides(position)`, plus les index FK auto-créés par MySQL sur chaque colonne `*_id`.

## Unicité manquante

### `rooms.name` 🔴
Aucune contrainte. Cas réel en base : id 12 et id 13 portent tous deux `name = '106'`, 18 `assignments` pointent vers id 12, 0 vers id 13.

`SchedulerImportController::preview()` (L.111) et `executeImport()` (L.237) font :
```php
$roomsByName = Room::whereIn('name', $localNames)->pluck('id', 'name');
```
`pluck('id', 'name')` construit un tableau associatif : à clé `name` égale, la **dernière** ligne itérée écrase la précédente. Sans `ORDER BY` explicite, MySQL ne garantit pas l'ordre, mais en pratique (InnoDB, pas de `ORDER BY`) c'est l'ordre des clés primaires — donc l'id le plus élevé (13, celui qui n'a **aucun** assignment) gagne. Conséquence concrète et déjà vérifiable : un futur import Excel réimportant des séances sur le local « 106 » les rattacherait à l'id 13, alors que les 18 séances existantes du vrai local 106 restent sur l'id 12 — deux fils de planning parallèles pour la même salle physique, invisibles l'un à l'autre. Idem pour `RoomController::index` (`Room::all()`) et le sélecteur de salle dans `ScheduleController::index` : le local apparaît deux fois dans les listes déroulantes, l'utilisateur choisit arbitrairement l'un ou l'autre.

### `groups.name` 🔴
Aucune contrainte. Doublon réel : `BAC Développeur d'applications` en id 7 et 8. `CourseController::create/edit` liste `Group::all()` sans dédoublonnage — le groupe apparaît deux fois dans le picker, un cours peut être rattaché au « mauvais » exemplaire, et `GroupController::show` (liste des cours du groupe) ne montrera qu'une partie des cours réellement liés à ce groupe logique.

### `teachers.name` 🔴
Aucune contrainte. Doublon réel : `Dupont Marie` en id 4 et 7. `courses.teacher_id` est une FK simple vers un seul id : des cours créés à des moments différents pour « Dupont Marie » peuvent pointer vers 4 ou 7 selon lequel apparaît en premier dans `Teacher::all()` au moment de la saisie. `TeacherController::show` (L.55, `$teacher->load('courses')`) n'affichera alors qu'une partie des cours du même enseignant physique.

### `course_group` (composite `course_id` + `group_id`) 🟢
`Course::groups()->sync(...)` (CourseController::store/update) protège déjà contre les doublons *au sein d'un même appel* (sync fait un diff), donc le risque fonctionnel est faible. Mais rien n'empêche un insert direct ou une race condition de dupliquer une ligne de pivot ; aucune donnée dupliquée trouvée actuellement (6 lignes, toutes distinctes). Défense en profondeur, pas urgent.

## Unicité composite sur `assignments` (date, period, room_id)

Le code traite bien ce trio comme la définition d'un créneau, à trois endroits, **aucun garanti par la base** :

1. `ScheduleController::slotIsOccupied()` (L.225-237) : `SELECT ... WHERE room_id=? AND date=? AND period=? EXISTS` avant `store()`/`update()`. C'est un pattern **check-then-act** : entre le `SELECT` et l'`INSERT`, rien n'empêche deux requêtes concurrentes (deux membres du staff qui posent une séance au même moment) de passer toutes les deux le contrôle et d'insérer deux `assignments` sur le même créneau. Sans contrainte DB, MySQL accepte les deux lignes sans broncher.
2. `ScheduleController::bulkStore()` (L.178-210) : `DELETE ... WHERE room_id/date/period` puis `INSERT`, dans une transaction — mais rien n'empêche une requête `store()` concurrente de s'insérer entre le `DELETE` et l'`INSERT` du bulk, ou deux bulk imports simultanés de s'entrelacer.
3. `SchedulerImportController::preview()`/`executeImport()` (L.131-135, L.258-261) : `Assignment::where('date')->where('period')->where('room_id')->first()` — si des doublons de créneau existent déjà (possible vu l'absence de contrainte), `first()` en ignore silencieusement une partie sans le signaler.

Sous charge concurrente réelle (import Excel qui tourne pendant qu'un utilisateur édite le planning à la main, ou deux onglets ouverts), deux `assignments` différents peuvent finir sur le même (date, period, room_id) : l'écran TV (`ScreenController::data()`, requête `where('date', ...)->whereIn('period', ...)`) affichera alors deux cours pour le même créneau/salle sans qu'aucune erreur ne remonte.

`room_id` est **nullable** (`assignments.room_id`). Point important pour la contrainte à ajouter : en MySQL, un index UNIQUE traite chaque `NULL` comme distinct des autres — plusieurs lignes avec `room_id IS NULL` sur le même (date, period) ne violeraient donc **pas** un `unique(date, period, room_id)`. C'est cohérent avec le métier : sans salle assignée, il n'y a pas de conflit physique possible.

Bonus : cette contrainte (ordonnée `date, period, room_id`) sert aussi d'index pour la requête la plus chaude de l'appli — `ScreenController::data()` filtre par `date` puis `period` en continu (polling des écrans TV) — et rend redondant l'index actuel `assignments(date, room_id)`.

## Index

- **`assignments(date, period, room_id)` 🔴** — voir ci-dessus ; remplace avantageusement `assignments_date_room_id_index` (aucune requête du code ne filtre `date + room_id` sans `period`).
- **`assignments.status` 🟢** — index existant, mais aucun `where('status', ...)` trouvé côté serveur (grep sur `app/Http/Controllers`) ; le filtrage semble se faire côté front après récupération. Pas nuisible, probablement juste inutilisé aujourd'hui — à laisser, ne pas investir dessus.
- **`course_group`** — les FK sur `course_id` et `group_id` sont déjà indexées individuellement par MySQL (auto-index de clé étrangère) ; l'ajout de l'unique composite (ci-dessus) sert aussi d'index de couverture pour un lookup exact de paire, mais ce n'est pas un besoin de performance identifié.

## Clés étrangères

Toutes les relations *déclarées comme telles dans les modèles* sont bien contraintes en base (`courses.teacher_id`, `assignments.course_id`, `assignments.room_id`, `course_group.course_id/group_id`). Pas de FK manquante.

Un point de cohérence métier discutable : **`assignments.room_id` est en `cascadeOnDelete()`** alors que **`courses.teacher_id` est en `nullOnDelete()`**. Les deux situations sont pourtant analogues (« la ressource référencée disparaît, que devient l'enregistrement qui la référence ? ») et traitées différemment :
- Supprimer un `Teacher` → ses `courses` survivent, `teacher_id` passe à `NULL` (correct : un cours ne doit pas disparaître parce qu'on supprime la fiche enseignant).
- Supprimer un `Room` → **tous ses `assignments` sont supprimés en cascade**, silencieusement, sans avertissement dans `RoomController::destroy()` (pas de vérification du nombre d'assignments liés avant suppression). Un admin qui supprime un local par erreur (ou pour le renommer en recréant une fiche, cas plausible vu le bug de doublons ci-dessus) efface d'un coup tout l'historique de planning de ce local.

🟠 Recommandation : passer `assignments.room_id` en `nullOnDelete()` (comme `teacher_id`), ce qui est cohérent avec le fait que la colonne est déjà nullable — une séance perd sa salle mais n'est pas perdue. Alternative moins invasive : garder `cascadeOnDelete()` mais ajouter un contrôle applicatif dans `RoomController::destroy()` (hors du périmètre DB de cet audit, mais à tracer).

## Types et nullabilité

- **`period` et `status`** sont des `enum` MySQL avec les valeurs dupliquées en dur dans au moins 5 endroits (`StoreScheduleAssignmentRequest`, `UpdateScheduleAssignmentRequest`, `UpdateScheduleAssignmentStatusRequest`, `ScheduleController::bulkPreview/bulkStore`, `ScreenController::PERIOD_TIMES/PERIOD_LABELS`). Fonctionnellement correct pour un jeu de valeurs stable et restreint ; à noter seulement si le métier envisage d'ajouter une période un jour (ex. un service actuellement une modification de schéma + code partout). 🟢 pas d'action DB requise maintenant.
- **`rooms.name` / `groups.name` / `teachers.name`** en `varchar(255)` — largement surdimensionné pour des noms de salle/groupe/enseignant, mais inoffensif. 🟢 confort seulement, pas nécessaire de resserrer.
- **`screen_slides.position`** (`unsigned int NOT NULL`) n'a pas d'unicité, mais **ne doit probablement pas en avoir une classique** : `ScreenSlideController::reorder()` et `::destroy()` réassignent les positions ligne par ligne dans une transaction (`foreach ($orderedIds as $position => $id) { ...->update(['position' => $position]) }`). Si deux lignes échangent leurs positions, la première `UPDATE` de la boucle entrerait en collision avec la position pas-encore-libérée de l'autre ligne si une contrainte `UNIQUE` stricte était posée telle quelle — MySQL n'a pas de contrainte différée (`DEFERRABLE`) comme PostgreSQL. Ne pas ajouter `unique(position)` sans réécrire `reorder()`/`destroy()` en deux passes (ex. décaler d'abord toutes les positions vers une plage négative temporaire, puis les fixer à leur valeur finale, dans la même transaction). 🟢 possible mais pas gratuit — le documenter plutôt que le faire à la légère.
- **`assignments.room_id` nullable** — cohérent avec `AssignmentFactory` (`fake()->boolean(90) ? Room::factory() : null`), mais aucun flux applicatif observé ne crée réellement une `assignment` sans salle (`StoreScheduleAssignmentRequest.room_id` est `required`, `bulkStore` aussi). La nullabilité existe uniquement pour survivre à une suppression de salle (cf. section FK) — cohérent avec la recommandation `nullOnDelete()` ci-dessus, incohérent si on garde `cascadeOnDelete()` (la colonne n'aurait alors plus de raison d'être nullable).

## Migrations proposées

### 1. 🔴 Dédoublonnage + unicité `rooms.name`
```php
Schema::table('rooms', function (Blueprint $table) {
    // reporter les assignments des doublons vers l'id canonique (le plus petit id)
});

DB::table('rooms')
    ->select('name')
    ->groupBy('name')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('name')
    ->each(function (string $name) {
        $ids = DB::table('rooms')->where('name', $name)->orderBy('id')->pluck('id');
        $canonical = $ids->shift();

        DB::table('assignments')->whereIn('room_id', $ids)->update(['room_id' => $canonical]);
        DB::table('rooms')->whereIn('id', $ids)->delete();
    });

Schema::table('rooms', function (Blueprint $table) {
    $table->unique('name');
});
```

### 2. 🔴 Dédoublonnage + unicité `teachers.name`
```php
DB::table('teachers')
    ->select('name')
    ->groupBy('name')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('name')
    ->each(function (string $name) {
        $ids = DB::table('teachers')->where('name', $name)->orderBy('id')->pluck('id');
        $canonical = $ids->shift();

        DB::table('courses')->whereIn('teacher_id', $ids)->update(['teacher_id' => $canonical]);
        DB::table('teachers')->whereIn('id', $ids)->delete();
    });

Schema::table('teachers', function (Blueprint $table) {
    $table->unique('name');
});
```

### 3. 🔴 Dédoublonnage + unicité `groups.name` (+ nettoyage du pivot)
```php
DB::table('groups')
    ->select('name')
    ->groupBy('name')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('name')
    ->each(function (string $name) {
        $ids = DB::table('groups')->where('name', $name)->orderBy('id')->pluck('id');
        $canonical = $ids->shift();

        DB::table('course_group')->whereIn('group_id', $ids)->update(['group_id' => $canonical]);
        DB::table('groups')->whereIn('id', $ids)->delete();
    });

// un même course_id peut désormais apparaître deux fois avec group_id = $canonical
// (le groupe fusionné était déjà lié au même cours sous les deux anciens ids)
DB::table('course_group')
    ->select('course_id', 'group_id')
    ->groupBy('course_id', 'group_id')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->each(function ($row) {
        $ids = DB::table('course_group')
            ->where('course_id', $row->course_id)
            ->where('group_id', $row->group_id)
            ->orderBy('id')
            ->pluck('id');
        DB::table('course_group')->whereIn('id', $ids->slice(1))->delete();
    });

Schema::table('groups', function (Blueprint $table) {
    $table->unique('name');
});
```

### 4. 🟢 Unicité composite `course_group` (course_id, group_id)
```php
// Aucun doublon constaté actuellement (6 lignes distinctes) : pas de dédoublonnage nécessaire
// avant cette migration si elle est appliquée juste après la migration 3.
Schema::table('course_group', function (Blueprint $table) {
    $table->unique(['course_id', 'group_id']);
});
```

### 5. 🔴 Créneau unique sur `assignments` (date, period, room_id)
```php
// Dédoublonnage défensif : garder l'assignment le plus ancien par créneau occupé,
// supprimer les autres (aucun doublon constaté aujourd'hui, mais rien ne l'empêchait).
DB::table('assignments')
    ->select('date', 'period', 'room_id')
    ->whereNotNull('room_id')
    ->groupBy('date', 'period', 'room_id')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->each(function ($row) {
        $ids = DB::table('assignments')
            ->where('date', $row->date)
            ->where('period', $row->period)
            ->where('room_id', $row->room_id)
            ->orderBy('id')
            ->pluck('id');
        DB::table('assignments')->whereIn('id', $ids->slice(1))->delete();
    });

Schema::table('assignments', function (Blueprint $table) {
    $table->dropIndex('assignments_date_room_id_index');
    $table->unique(['date', 'period', 'room_id']);
});
```
Note : `room_id IS NULL` n'entre pas en conflit (sémantique NULL-distinct de MySQL), donc pas de dédoublonnage nécessaire côté lignes sans salle.

### 6. 🟠 Cohérence FK `assignments.room_id`
```php
Schema::table('assignments', function (Blueprint $table) {
    $table->dropForeign(['room_id']);
    $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
});
```

## Ordre d'application et déduplication

1. **Migration 1** (rooms) — indépendante, peut passer en premier.
2. **Migration 2** (teachers) — indépendante.
3. **Migration 3** (groups + nettoyage pivot) — doit précéder la migration 4 (sinon l'unique composite sur `course_group` peut échouer si la fusion de groupes crée un doublon de paire).
4. **Migration 4** (unique `course_group`) — après 3.
5. **Migration 5** (unique `assignments`) — indépendante des précédentes, mais à tester après un import Excel réel pour vérifier qu'aucun créneau dupliqué ne bloque le déploiement.
6. **Migration 6** (FK `room_id` → nullOnDelete) — peut passer n'importe quand, idéalement avant 5 pour ne pas mélanger deux ALTER TABLE sur la même table dans une fenêtre de déploiement serrée ; sans impact sur la déduplication.

Toutes les étapes de déduplication ci-dessus sont **idempotentes et sans perte de données** (fusion vers l'id le plus ancien, jamais de suppression d'une ligne sans avoir d'abord reporté ses références). Elles doivent tourner dans la même migration que l'ajout de la contrainte (comme montré), pas dans une migration séparée, pour éviter une fenêtre où la contrainte existe déjà mais les doublons non.
